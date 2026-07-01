<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\services\cleaning\CleaningService;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Admin area (login-gated): the cleaning funnel. Shows how many keywords survive each stage and
 * why the rest were dropped, and runs the pipeline on demand. Thin controller — all logic lives
 * in {@see CleaningService}.
 */
class CleaningController extends Controller
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

    /** The funnel dashboard for the last cleaning run (empty state until the first run). */
    public function actionIndex(): string
    {
        return $this->render('index', ['summary' => CleaningService::snapshot()]);
    }

    /** Run the cleaning pipeline over all keywords, then show the refreshed funnel. */
    public function actionRun(): Response
    {
        $summary = CleaningService::create()->run();
        $dropped = $summary['dropped'];

        Yii::$app->session->setFlash(
            'success',
            sprintf(
                'Cleaning done: %d of %d keywords kept. Dropped %d junk, %d duplicate, %d brand, %d below volume.',
                $summary['survivors'],
                $summary['total'],
                $dropped['junk'],
                $dropped['duplicate'],
                $dropped['brand'],
                $dropped['below_volume'],
            ),
        );

        return $this->redirect(['index']);
    }
}
