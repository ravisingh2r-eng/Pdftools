<?php
/**
 * PDF Optimize Service Tests
 */

use PDFTools\Pdf\PdfOptimizeService;

function test_compress_basic_creates_output()
{
    $service = new PdfOptimizeService(TEST_TEMP_DIR);

    $input = TEST_TEMP_DIR . '/compress_input.pdf';
    $output = TEST_TEMP_DIR . '/compress_output.pdf';

    create_test_pdf($input, 'Compression Test', 3);

    $result = $service->compressBasic($input, $output, 'medium');

    assert_true($result, 'Compression should succeed');
    assert_file_exists($output, 'Output file should exist');

    @unlink($input);
    @unlink($output);
}

function test_compress_invalid_mode_throws_exception()
{
    $service = new PdfOptimizeService(TEST_TEMP_DIR);

    $input = TEST_TEMP_DIR . '/invalid_mode.pdf';
    create_test_pdf($input, 'Test', 1);

    assert_throws(function() use ($service, $input) {
        $service->compressBasic($input, TEST_TEMP_DIR . '/output.pdf', 'invalid');
    }, 'InvalidArgumentException', 'Invalid mode should throw exception');

    @unlink($input);
}

function test_remove_metadata_creates_clean_pdf()
{
    $service = new PdfOptimizeService(TEST_TEMP_DIR);

    $input = TEST_TEMP_DIR . '/metadata_input.pdf';
    $output = TEST_TEMP_DIR . '/metadata_output.pdf';

    create_test_pdf($input, 'Metadata Test', 1);

    $result = $service->removeMetadata($input, $output);

    assert_true($result, 'Remove metadata should succeed');
    assert_file_exists($output, 'Output file should exist');

    @unlink($input);
    @unlink($output);
}

function test_remove_annotations_creates_clean_pdf()
{
    $service = new PdfOptimizeService(TEST_TEMP_DIR);

    $input = TEST_TEMP_DIR . '/annot_input.pdf';
    $output = TEST_TEMP_DIR . '/annot_output.pdf';

    create_test_pdf($input, 'Annotations Test', 2);

    $result = $service->removeAnnotations($input, $output);

    assert_true($result, 'Remove annotations should succeed');
    assert_file_exists($output, 'Output file should exist');

    @unlink($input);
    @unlink($output);
}

function test_flatten_creates_output()
{
    $service = new PdfOptimizeService(TEST_TEMP_DIR);

    $input = TEST_TEMP_DIR . '/flatten_input.pdf';
    $output = TEST_TEMP_DIR . '/flatten_output.pdf';

    create_test_pdf($input, 'Flatten Test', 1);

    $result = $service->flatten($input, $output);

    assert_true($result, 'Flatten should succeed');
    assert_file_exists($output, 'Output file should exist');

    @unlink($input);
    @unlink($output);
}

function test_get_compression_stats()
{
    $service = new PdfOptimizeService(TEST_TEMP_DIR);

    $input = TEST_TEMP_DIR . '/stats_input.pdf';
    $output = TEST_TEMP_DIR . '/stats_output.pdf';

    create_test_pdf($input, 'Stats Test', 5);
    $service->compressBasic($input, $output, 'high');

    $stats = $service->getCompressionStats($input, $output);

    assert_true(isset($stats['original_size']), 'Stats should have original_size');
    assert_true(isset($stats['compressed_size']), 'Stats should have compressed_size');
    assert_true(isset($stats['reduction_percent']), 'Stats should have reduction_percent');
    assert_greater_than(0, $stats['original_size'], 'Original size should be > 0');

    @unlink($input);
    @unlink($output);
}

function test_compression_modes_are_defined()
{
    $modes = PdfOptimizeService::COMPRESSION_MODES;

    assert_true(isset($modes['low']), 'Low mode should exist');
    assert_true(isset($modes['medium']), 'Medium mode should exist');
    assert_true(isset($modes['high']), 'High mode should exist');
}
