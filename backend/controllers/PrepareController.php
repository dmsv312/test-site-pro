<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\services\preparation\PreparationService;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Admin area (login-gated): the preparation funnel and campaign preview. Shows how the cleaned
 * keywords become a net-new, campaign-ready set — dropping already-used and forbidden terms, then
 * grouping the survivors into one campaign per language with themed ad groups. Thin controller —
 * all logic lives in {@see PreparationService}.
 */
class PrepareController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['run' => ['post']],
            ],
        ];
    }

    /** The preparation funnel + campaign preview for the last run (empty state until the first run). */
    public function actionIndex(): string
    {
        return $this->render('index', ['summary' => PreparationService::snapshot()]);
    }

    /** Run preparation (drops → merge → group) over the cleaned keywords, then show the result. */
    public function actionRun(): Response
    {
        $summary = PreparationService::create()->run();
        $dropped = $summary['dropped'];
        $grouping = $summary['grouping'];

        Yii::$app->session->setFlash(
            'success',
            sprintf(
                'Preparation done: %d of %d candidates prepared (dropped %d already-used, %d forbidden), '
                . 'grouped into %d ad group(s) across %d language(s). '
                . 'Ad generation was reset — re-run it to refresh the ads.',
                $summary['prepared'],
                $summary['candidates'],
                $dropped['already_used'],
                $dropped['forbidden'],
                $grouping['adGroups'],
                $grouping['languages'],
            ),
        );

        return $this->redirect(['index']);
    }
}
