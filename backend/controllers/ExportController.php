<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\services\export\ExportService;
use app\services\export\GoogleAdsEditorExport;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;

/**
 * Admin area (login-gated): stage 7 — campaign preview and Google Ads Editor CSV download. Thin
 * controller; the preview numbers and the CSV both come from {@see ExportService}. The download is a
 * read-only GET (a file the operator clicks to save), so it has no CSRF form; access is still gated.
 */
class ExportController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
        ];
    }

    /** The export preview (campaigns → ad groups, counts, and what the file will contain). */
    public function actionIndex(): string
    {
        return $this->render('index', ['summary' => ExportService::snapshot()]);
    }

    /** Stream the Google Ads Editor CSV as a download, or bounce back with a notice if it's empty. */
    public function actionDownload(): Response
    {
        $rows = (new ExportService())->rows();
        $hasAd = array_filter($rows, static fn (array $row): bool => isset($row['Ad Type'])) !== [];

        if (!$hasAd) {
            Yii::$app->session->setFlash(
                'warning',
                'Nothing to export yet — run preparation and ad generation first.',
            );

            return $this->redirect(['index']);
        }

        return Yii::$app->response->sendContentAsFile(
            GoogleAdsEditorExport::render($rows),
            'google-ads-editor-' . date('Ymd') . '.csv',
            ['mimeType' => 'text/csv', 'inline' => false],
        );
    }
}
