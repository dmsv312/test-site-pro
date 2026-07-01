<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Export;

use app\services\export\GoogleAdsEditorExport;

final class GoogleAdsEditorExportTest extends \Codeception\Test\Unit
{
    public function testHeaderCoversBothRowTypes(): void
    {
        $header = GoogleAdsEditorExport::header();

        verify(in_array('Campaign', $header, true))->true();
        verify(in_array('Ad Group', $header, true))->true();
        verify(in_array('Keyword', $header, true))->true();
        verify(in_array('Match Type', $header, true))->true();
        verify(in_array('Ad Type', $header, true))->true();
        verify(in_array('Headline 1', $header, true))->true();
        verify(in_array('Headline 15', $header, true))->true();
        verify(in_array('Description 4', $header, true))->true();
        verify(in_array('Final URL', $header, true))->true();
        // No Headline 16 / Description 5 — the RSA ceiling is respected.
        verify(in_array('Headline 16', $header, true))->false();
        verify(in_array('Description 5', $header, true))->false();
    }

    public function testKeywordRowDefaultsToPhraseAndSanitizes(): void
    {
        $row = GoogleAdsEditorExport::keywordRow(
            'Site.pro — EN',
            'Website',
            "  Free   WEBSITE\tBuilder ",   // messy case + whitespace + tab
            'https://site.pro/',
        );

        verify($row)->notNull();
        verify($row['Keyword'])->equals('free website builder');
        verify($row['Match Type'])->equals('Phrase');
        verify($row['Campaign Type'])->equals('Search');
        verify($row['Final URL'])->equals('https://site.pro/');
    }

    public function testKeywordRowStripsControlCharacters(): void
    {
        $row = GoogleAdsEditorExport::keywordRow('C', 'G', "web\x07site", 'https://site.pro/');

        verify($row)->notNull();
        verify($row['Keyword'])->equals('website');
    }

    public function testKeywordRowIsNullWhenTermEmptyAfterSanitizing(): void
    {
        verify(GoogleAdsEditorExport::keywordRow('C', 'G', "  \x00 \t ", 'https://site.pro/'))->null();
    }

    public function testKeywordRowRespectsExplicitMatchType(): void
    {
        $row = GoogleAdsEditorExport::keywordRow('C', 'G', 'website builder', 'https://site.pro/', 'Exact');

        verify($row['Match Type'])->equals('Exact');
    }

    public function testAdRowSpreadsHeadlinesAndDescriptions(): void
    {
        $row = GoogleAdsEditorExport::adRow(
            'Site.pro — DE',
            'Erstellen',
            ['Headline A', 'Headline B', 'Headline C'],
            ['Description one', 'Description two'],
            'de',
            null,
            'https://site.pro/de/',
        );

        verify($row['Ad Type'])->equals('Responsive search ad');
        verify($row['Headline 1'])->equals('Headline A');
        verify($row['Headline 3'])->equals('Headline C');
        verify($row['Description 1'])->equals('Description one');
        verify($row['Description 2'])->equals('Description two');
        verify($row['Path 1'])->equals('de');
        verify($row['Path 2'])->equals('');
        verify($row['Final URL'])->equals('https://site.pro/de/');
        // Nothing spilled past the supplied copy.
        verify(isset($row['Headline 4']))->false();
        verify(isset($row['Description 3']))->false();
    }

    public function testAdRowCapsAtRsaCeilings(): void
    {
        $headlines = array_map(static fn (int $i): string => "H{$i}", range(1, 20));
        $descriptions = array_map(static fn (int $i): string => "D{$i}", range(1, 6));

        $row = GoogleAdsEditorExport::adRow('C', 'G', $headlines, $descriptions, null, null, 'https://site.pro/');

        verify($row['Headline 15'])->equals('H15');
        verify(isset($row['Headline 16']))->false();
        verify($row['Description 4'])->equals('D4');
        verify(isset($row['Description 5']))->false();
    }

    public function testRenderStartsWithHeaderAndCountsRows(): void
    {
        $rows = [
            GoogleAdsEditorExport::keywordRow('C', 'G', 'website builder', 'https://site.pro/'),
            GoogleAdsEditorExport::adRow('C', 'G', ['a', 'b', 'c'], ['d1', 'd2'], null, null, 'https://site.pro/'),
        ];

        $csv = GoogleAdsEditorExport::render(array_filter($rows));
        $lines = array_values(array_filter(explode("\r\n", $csv), static fn (string $l): bool => $l !== ''));

        verify(count($lines))->equals(3); // header + 2 rows
        // First line is the full header (fields with spaces are quoted; parse it back to compare).
        verify(str_getcsv($lines[0], ',', '"', ''))->equals(GoogleAdsEditorExport::header());
        verify(str_starts_with($csv, 'Campaign,'))->true();
    }

    public function testRenderUsesCrlfLineEndings(): void
    {
        $csv = GoogleAdsEditorExport::render([
            GoogleAdsEditorExport::keywordRow('C', 'G', 'website', 'https://site.pro/'),
        ]);

        verify(str_contains($csv, "\r\n"))->true();
    }

    public function testRenderQuotesFieldsWithCommasAndQuotes(): void
    {
        // A headline carrying a comma and a double-quote must be RFC-4180 quoted: wrapped in quotes
        // with the inner quote doubled — never split into extra columns.
        $row = GoogleAdsEditorExport::adRow(
            'C',
            'G',
            ['Say "hi", now', 'b', 'c'],
            ['d1', 'd2'],
            null,
            null,
            'https://site.pro/',
        );

        $csv = GoogleAdsEditorExport::render([$row]);

        verify(str_contains($csv, '"Say ""hi"", now"'))->true();
    }
}
