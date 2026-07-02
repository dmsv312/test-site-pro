<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\services\adgen\AdGenerationService;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Admin area (login-gated): stage 6 — generate and preview a responsive search ad per ad group.
 * Thin controller; all logic lives in {@see AdGenerationService}. The preview shows each campaign's
 * ads (headlines / descriptions with character counts, target URL, and whether the copy came from
 * stored offline-authored content or the template fallback).
 */
class AdsController extends Controller
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

    /** The generated-ads preview (empty state until the first run). */
    public function actionIndex(): string
    {
        return $this->render('index', ['summary' => AdGenerationService::snapshot()]);
    }

    /** (Re)generate an ad for every ad group, then show the result. */
    public function actionRun(): Response
    {
        $summary = AdGenerationService::create()->run();

        Yii::$app->session->setFlash(
            'success',
            sprintf(
                'Created %d ad(s) across %d language(s): %d curated, %d from templates%s.',
                $summary['generated'],
                $summary['languages'],
                $summary['byStored'],
                $summary['byTemplate'],
                $summary['invalid'] > 0 ? ", {$summary['invalid']} need attention" : '',
            ),
        );

        return $this->redirect(['index']);
    }
}
