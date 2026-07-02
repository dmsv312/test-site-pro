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
 * Admin area (login-gated): stage 7 — campaign preview and two downloads. Thin controller; the
 * preview numbers and both artifacts come from {@see ExportService}. `download` streams the single
 * Google Ads Editor (desktop) CSV; `download-bulk` streams the Google Ads web-UI bulk-upload ZIP.
 * Both are read-only GETs (files the operator clicks to save), so no CSRF form; access is still gated.
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
        $hasAd = array_filter($rows, static fn (array $row): bool => isset($row['Headline 1'])) !== [];

        if (!$hasAd) {
            Yii::$app->session->setFlash(
                'warning',
                'Nothing to export yet — run preparation and ad generation first.',
            );

            return $this->redirect(['index']);
        }

        return Yii::$app->response->sendContentAsFile(
            GoogleAdsEditorExport::render($rows),
            'google-ads-editor-import-' . date('Ymd') . '.csv',
            ['mimeType' => 'text/csv', 'inline' => false],
        );
    }

    /**
     * Stream the Google Ads **web-UI** bulk-upload package (a ZIP of one CSV per entity —
     * campaigns / ad groups / keywords / ads — plus a README), or bounce back if there's nothing yet.
     */
    public function actionDownloadBulk(): Response
    {
        $service = new ExportService();
        if ((int) ExportService::snapshot()['adRows'] === 0) {
            Yii::$app->session->setFlash(
                'warning',
                'Nothing to export yet — run preparation and ad generation first.',
            );

            return $this->redirect(['index']);
        }

        $zip = $service->toBulkZip();
        if ($zip === '') {
            Yii::$app->session->setFlash('error', 'Could not build the bulk-upload archive.');

            return $this->redirect(['index']);
        }

        return Yii::$app->response->sendContentAsFile(
            $zip,
            'google-ads-bulk-upload-' . date('Ymd') . '.zip',
            ['mimeType' => 'application/zip', 'inline' => false],
        );
    }
}
