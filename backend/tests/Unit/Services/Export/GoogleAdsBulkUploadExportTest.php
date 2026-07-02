<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Export;

use app\services\export\CsvWriter;
use app\services\export\GoogleAdsBulkUploadExport as Bulk;

final class GoogleAdsBulkUploadExportTest extends \Codeception\Test\Unit
{
    public function testCampaignHeaderAndRowAreAPausedSearchStub(): void
    {
        $header = Bulk::campaignHeader();
        verify($header)->equals(['Action', 'Campaign status', 'Campaign', 'Campaign type', 'Bid strategy type']);

        $row = Bulk::campaignRow('Site.pro — EN');
        verify($row['Action'])->equals('Add');
        verify($row['Campaign status'])->equals('Paused');          // never serves by accident
        verify($row['Campaign'])->equals('Site.pro — EN');
        verify($row['Campaign type'])->equals('Search');
        verify($row['Bid strategy type'])->equals('Manual CPC');    // valid without a target; no budget column
        verify(isset($row['Budget']))->false();
    }

    public function testAdGroupHeaderAndRow(): void
    {
        verify(Bulk::adGroupHeader())->equals(['Action', 'Campaign', 'Ad group', 'Status']);

        $row = Bulk::adGroupRow('Site.pro — DE', 'Erstellen');
        verify($row['Action'])->equals('Add');
        verify($row['Campaign'])->equals('Site.pro — DE');
        verify($row['Ad group'])->equals('Erstellen');
        verify($row['Status'])->equals('Enabled');
    }

    public function testKeywordRowUsesWebMatchTypeAndSanitizes(): void
    {
        verify(Bulk::keywordHeader())->equals(['Action', 'Campaign', 'Ad group', 'Keyword', 'Match Type', 'Final URL']);

        $row = Bulk::keywordRow('Site.pro — EN', 'Website', "  Free   WEBSITE\tBuilder ", 'https://site.pro/');
        verify($row)->notNull();
        verify($row['Keyword'])->equals('free website builder');
        verify($row['Match Type'])->equals('Phrase match');   // web spelling, not the Editor's bare "Phrase"
        verify($row['Final URL'])->equals('https://site.pro/');
    }

    public function testKeywordRowIsNullWhenEmptyAfterSanitizing(): void
    {
        verify(Bulk::keywordRow('C', 'G', "  \x00 \t ", 'https://site.pro/'))->null();
    }

    public function testRsaHeaderNamesFirstDescriptionWithoutANumber(): void
    {
        $header = Bulk::rsaHeader();

        verify(in_array('Ad type', $header, true))->true();
        verify(in_array('Headline 1', $header, true))->true();
        verify(in_array('Headline 15', $header, true))->true();
        verify(in_array('Headline 16', $header, true))->false();
        // Google's template names the first description column just "Description", then 2..4.
        verify(in_array('Description', $header, true))->true();
        verify(in_array('Description 1', $header, true))->false();
        verify(in_array('Description 2', $header, true))->true();
        verify(in_array('Description 4', $header, true))->true();
        verify(in_array('Description 5', $header, true))->false();
        verify(in_array('Final URL', $header, true))->true();
    }

    public function testRsaRowIsPausedRsaWithCorrectDescriptionColumns(): void
    {
        $row = Bulk::rsaRow(
            'Site.pro — DE',
            'Erstellen',
            ['Headline A', 'Headline B', 'Headline C'],
            ['Desc one', 'Desc two', 'Desc three'],
            'de',
            null,
            'https://site.pro/de/',
        );

        verify($row['Action'])->equals('Add');
        verify($row['Ad status'])->equals('Paused');
        verify($row['Ad type'])->equals('Responsive search ad');
        verify($row['Headline 1'])->equals('Headline A');
        verify($row['Headline 3'])->equals('Headline C');
        verify($row['Description'])->equals('Desc one');       // unnumbered first description
        verify($row['Description 2'])->equals('Desc two');
        verify($row['Description 3'])->equals('Desc three');
        verify($row['Path 1'])->equals('de');
        verify($row['Path 2'])->equals('');
        verify($row['Final URL'])->equals('https://site.pro/de/');
        verify(isset($row['Headline 4']))->false();
        verify(isset($row['Description 4']))->false();
    }

    public function testRsaRowCapsAtRsaCeilings(): void
    {
        $headlines = array_map(static fn (int $i): string => "H{$i}", range(1, 20));
        $descriptions = array_map(static fn (int $i): string => "D{$i}", range(1, 6));

        $row = Bulk::rsaRow('C', 'G', $headlines, $descriptions, null, null, 'https://site.pro/');

        verify($row['Headline 15'])->equals('H15');
        verify(isset($row['Headline 16']))->false();
        verify($row['Description'])->equals('D1');
        verify($row['Description 4'])->equals('D4');
        verify(isset($row['Description 5']))->false();
    }

    public function testCsvWriterRendersHeaderCrlfAndRfc4180Quoting(): void
    {
        $header = Bulk::keywordHeader();
        $rows = [
            Bulk::keywordRow('Camp, Inc', 'G', 'say "hi" builder', 'https://site.pro/'),
        ];

        $csv = CsvWriter::render($header, array_filter($rows));
        $lines = array_values(array_filter(explode("\r\n", $csv), static fn (string $l): bool => $l !== ''));

        verify(count($lines))->equals(2);                                  // header + 1 row
        verify(str_getcsv($lines[0], ',', '"', ''))->equals($header);      // header round-trips
        verify(str_contains($csv, "\r\n"))->true();                        // CRLF
        verify(str_contains($csv, '"Camp, Inc"'))->true();                 // comma field quoted
        verify(str_contains($csv, '"say ""hi"" builder"'))->true();        // inner quotes doubled
    }
}
