<?php
/**
 * PDF Merge Service Tests
 */

use PDFTools\Pdf\PdfMergeService;

function test_merge_two_pdfs_creates_output()
{
    $service = new PdfMergeService(TEST_TEMP_DIR);

    // Create test PDFs
    $pdf1 = TEST_TEMP_DIR . '/test1.pdf';
    $pdf2 = TEST_TEMP_DIR . '/test2.pdf';
    $output = TEST_TEMP_DIR . '/merged.pdf';

    create_test_pdf($pdf1, 'Document 1', 2);
    create_test_pdf($pdf2, 'Document 2', 3);

    // Merge
    $result = $service->merge([$pdf1, $pdf2], $output);

    // Assertions
    assert_true($result, 'Merge should return true');
    assert_file_exists($output, 'Output file should exist');
    assert_greater_than(0, filesize($output), 'Output file should have content');

    // Clean up
    @unlink($pdf1);
    @unlink($pdf2);
    @unlink($output);
}

function test_merge_single_pdf_creates_copy()
{
    $service = new PdfMergeService(TEST_TEMP_DIR);

    $pdf = TEST_TEMP_DIR . '/single.pdf';
    $output = TEST_TEMP_DIR . '/single_merged.pdf';

    create_test_pdf($pdf, 'Single Document', 1);

    $result = $service->merge([$pdf], $output);

    assert_true($result, 'Merge of single file should succeed');
    assert_file_exists($output, 'Output file should exist');

    @unlink($pdf);
    @unlink($output);
}

function test_merge_empty_array_throws_exception()
{
    $service = new PdfMergeService(TEST_TEMP_DIR);

    assert_throws(function() use ($service) {
        $service->merge([], TEST_TEMP_DIR . '/output.pdf');
    }, 'InvalidArgumentException', 'Empty array should throw exception');
}

function test_merge_nonexistent_file_throws_exception()
{
    $service = new PdfMergeService(TEST_TEMP_DIR);

    assert_throws(function() use ($service) {
        $service->merge(['/nonexistent/file.pdf'], TEST_TEMP_DIR . '/output.pdf');
    }, 'InvalidArgumentException', 'Nonexistent file should throw exception');
}

function test_get_total_page_count()
{
    $service = new PdfMergeService(TEST_TEMP_DIR);

    $pdf1 = TEST_TEMP_DIR . '/count1.pdf';
    $pdf2 = TEST_TEMP_DIR . '/count2.pdf';

    create_test_pdf($pdf1, 'Test', 3);
    create_test_pdf($pdf2, 'Test', 5);

    $count = $service->getTotalPageCount([$pdf1, $pdf2]);

    assert_equals(8, $count, 'Total page count should be 8');

    @unlink($pdf1);
    @unlink($pdf2);
}

function test_merge_preserves_page_count()
{
    $service = new PdfMergeService(TEST_TEMP_DIR);

    $pdf1 = TEST_TEMP_DIR . '/preserve1.pdf';
    $pdf2 = TEST_TEMP_DIR . '/preserve2.pdf';
    $output = TEST_TEMP_DIR . '/preserve_merged.pdf';

    create_test_pdf($pdf1, 'Doc 1', 2);
    create_test_pdf($pdf2, 'Doc 2', 3);

    $service->merge([$pdf1, $pdf2], $output);

    // Check merged page count
    $splitService = new \PDFTools\Pdf\PdfSplitService(TEST_TEMP_DIR);
    $pageCount = $splitService->getPageCount($output);

    assert_equals(5, $pageCount, 'Merged PDF should have 5 pages');

    @unlink($pdf1);
    @unlink($pdf2);
    @unlink($output);
}
