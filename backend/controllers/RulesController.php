<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\BrandTerm;
use app\models\ForbiddenTerm;
use app\models\RuleConfig;
use app\models\TermListRecord;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Html;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Admin area (login-gated): edit the cleaning thresholds and the brand / forbidden term lists.
 * These are the rules the cleaning pipeline (stage 4) and preparation (stage 5) read, kept in
 * the database instead of hard-coded so they can be tuned without a deploy.
 */
class RulesController extends Controller
{
    /** list key → term-list model class */
    private const LISTS = [
        'brand' => BrandTerm::class,
        'forbidden' => ForbiddenTerm::class,
    ];

    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'save-config' => ['post'],
                    'add-term' => ['post'],
                    'delete-term' => ['post'],
                ],
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'thresholds' => RuleConfig::all(),
            'brandTerms' => BrandTerm::find()->orderBy(['term' => SORT_ASC])->all(),
            'forbiddenTerms' => ForbiddenTerm::find()->orderBy(['term' => SORT_ASC])->all(),
            'newBrand' => new BrandTerm(),
            'newForbidden' => new ForbiddenTerm(),
        ]);
    }

    /** Save the edited thresholds. Values are validated per row; invalid ones are reported. */
    public function actionSaveConfig(): Response
    {
        $posted = (array) (Yii::$app->request->post('RuleConfig')['value'] ?? []);
        $errors = [];
        $saved = 0;

        foreach ($posted as $name => $value) {
            $row = RuleConfig::findOne(['name' => $name]);
            if ($row === null) {
                continue;
            }
            $row->value = is_string($value) ? trim($value) : (string) $value;
            if ($row->save()) {
                $saved++;
            } else {
                $errors[] = ($row->label ?: $name) . ': ' . implode(' ', $row->getFirstErrors());
            }
        }

        if ($errors !== []) {
            Yii::$app->session->setFlash('error', 'Some thresholds were not saved. ' . implode(' ', $errors));
        } else {
            Yii::$app->session->setFlash('success', "Saved {$saved} threshold(s).");
        }

        return $this->redirect(['index']);
    }

    /** Add a term to the brand or forbidden list. */
    public function actionAddTerm(string $list): Response
    {
        $model = $this->newTerm($list);
        $model->load(Yii::$app->request->post());

        if ($model->save()) {
            Yii::$app->session->setFlash(
                'success',
                'Added “' . Html::encode($model->term) . "” to the {$list} list.",
            );
        } else {
            Yii::$app->session->setFlash('error', implode(' ', $model->getFirstErrors()));
        }

        return $this->redirect(['index']);
    }

    /** Remove a term from the brand or forbidden list. */
    public function actionDeleteTerm(string $list, int $id): Response
    {
        $model = $this->findTerm($list, $id);
        $term = $model->term;
        $model->delete();
        Yii::$app->session->setFlash(
            'success',
            'Removed “' . Html::encode($term) . "” from the {$list} list.",
        );

        return $this->redirect(['index']);
    }

    private function newTerm(string $list): TermListRecord
    {
        $class = self::LISTS[$list] ?? throw new NotFoundHttpException('Unknown list.');

        return new $class();
    }

    private function findTerm(string $list, int $id): TermListRecord
    {
        $class = self::LISTS[$list] ?? throw new NotFoundHttpException('Unknown list.');
        $model = $class::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Term not found.');
        }

        return $model;
    }
}
