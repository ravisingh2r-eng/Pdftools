<?php
/**
 * PDF Convert Service
 *
 * Handles PDF content extraction and conversion operations.
 *
 * @package PDFTools\Pdf
 */

namespace PDFTools\Pdf;

use setasign\Fpdi\Fpdi;
use InvalidArgumentException;
use Exception;

class PdfConvertService
{
    private string $tempDir;

    public function __construct(string $tempDir)
    {
        $this->tempDir = rtrim($tempDir, '/');
    }

    /**
     * Extract text from PDF
     *
     * @param string $filePath Source PDF
     * @return string Extracted text
     */
    public function pdfToText(string $filePath): string
    {
        $this->validateFile($filePath);

        $content = file_get_contents($filePath);
        $text = '';

        // Find all streams and extract text
        preg_match_all('/(\d+)\s+0\s+obj[^>]*>>\s*stream\s*(.*?)\s*endstream/s', $content, $streamMatches, PREG_SET_ORDER);

        foreach ($streamMatches as $match) {
            $streamData = $match[2];

            // Try to decompress
            $decoded = @gzuncompress($streamData);
            if ($decoded === false) {
                $decoded = @gzuncompress(substr($streamData, 2));
            }
            if ($decoded === false) {
                $decoded = $streamData;
            }

            // Extract text from BT...ET blocks
            if (preg_match('/BT\s.*?\sET/s', $decoded)) {
                $text .= $this->extractTextFromStream($decoded) . "\n\n";
            }
        }

        // Fallback: try raw content
        if (empty(trim($text))) {
            preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $textBlocks);
            foreach ($textBlocks[1] as $block) {
                $text .= $this->extractTextFromBlock($block) . "\n";
            }
        }

        return trim($text);
    }

    /**
     * Extract text from PDF organized by pages
     *
     * @param string $filePath Source PDF
     * @return array Array of text indexed by page number
     */
    public function pdfToTextByPage(string $filePath): array
    {
        $this->validateFile($filePath);

        $pageTexts = [];
        $content = file_get_contents($filePath);

        preg_match_all('/(\d+)\s+0\s+obj[^>]*>>\s*stream\s*(.*?)\s*endstream/s', $content, $streamMatches, PREG_SET_ORDER);

        $pageNum = 0;
        foreach ($streamMatches as $match) {
            $streamData = $match[2];

            $decoded = @gzuncompress($streamData);
            if ($decoded === false) {
                $decoded = @gzuncompress(substr($streamData, 2));
            }
            if ($decoded === false) {
                $decoded = $streamData;
            }

            if (preg_match('/BT\s.*?\sET/s', $decoded)) {
                $text = $this->extractTextFromStream($decoded);
                if (!empty(trim($text))) {
                    $pageTexts[$pageNum] = $text;
                    $pageNum++;
                }
            }
        }

        return $pageTexts;
    }

    /**
     * Extract links from PDF
     *
     * @param string $filePath Source PDF
     * @return array Array of found links
     */
    public function extractLinks(string $filePath): array
    {
        $this->validateFile($filePath);

        $content = file_get_contents($filePath);
        $links = [];

        // Find URI annotations
        preg_match_all('/\/URI\s*\((.*?)\)/s', $content, $uriMatches);
        foreach ($uriMatches[1] as $uri) {
            $uri = $this->decodePdfString($uri);
            if (!empty($uri) && !in_array($uri, $links)) {
                $links[] = $uri;
            }
        }

        // Find URLs in text content
        preg_match_all('/https?:\/\/[^\s\)\]<>"\']+/i', $content, $urlMatches);
        foreach ($urlMatches[0] as $url) {
            $url = rtrim($url, ').,;:');
            if (!in_array($url, $links)) {
                $links[] = $url;
            }
        }

        // Find mailto links
        preg_match_all('/mailto:[^\s\)\]<>"\']+/i', $content, $mailMatches);
        foreach ($mailMatches[0] as $mail) {
            if (!in_array($mail, $links)) {
                $links[] = $mail;
            }
        }

        return $links;
    }

    /**
     * Get PDF metadata
     *
     * @param string $filePath Source PDF
     * @return array Metadata array
     */
    public function getMetadata(string $filePath): array
    {
        $this->validateFile($filePath);

        $content = file_get_contents($filePath);
        $metadata = [];

        // Extract common metadata fields
        $fields = ['Title', 'Author', 'Subject', 'Keywords', 'Creator', 'Producer', 'CreationDate', 'ModDate'];

        foreach ($fields as $field) {
            if (preg_match('/\/' . $field . '\s*\((.*?)\)/s', $content, $match)) {
                $metadata[$field] = $this->decodePdfString($match[1]);
            } elseif (preg_match('/\/' . $field . '\s*<([^>]*)>/s', $content, $match)) {
                $metadata[$field] = $this->decodeHexString($match[1]);
            }
        }

        return $metadata;
    }

    /**
     * Get page count
     */
    public function getPageCount(string $filePath): int
    {
        $this->validateFile($filePath);
        $pdf = new Fpdi();
        return $pdf->setSourceFile($filePath);
    }

    /**
     * Extract text from a PDF stream
     */
    private function extractTextFromStream(string $stream): string
    {
        $text = '';
        preg_match_all('/BT\s*(.*?)\s*ET/s', $stream, $textBlocks);

        foreach ($textBlocks[1] as $block) {
            $text .= $this->extractTextFromBlock($block) . "\n";
        }

        return $text;
    }

    /**
     * Extract text from a BT...ET block
     */
    private function extractTextFromBlock(string $block): string
    {
        $text = '';

        // Extract Tj operators
        preg_match_all('/\((.*?)\)\s*Tj/s', $block, $tjMatches);
        foreach ($tjMatches[1] as $str) {
            $text .= $this->decodePdfString($str);
        }

        // Extract TJ operators
        preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $tjArrayMatches);
        foreach ($tjArrayMatches[1] as $arr) {
            preg_match_all('/\((.*?)\)/', $arr, $strings);
            foreach ($strings[1] as $str) {
                $text .= $this->decodePdfString($str);
            }
        }

        // Add spacing for positioning operators
        if (preg_match('/Td|TD|T\*|\'|"/', $block)) {
            $text .= ' ';
        }

        return $text;
    }

    /**
     * Decode PDF string escapes
     */
    private function decodePdfString(string $str): string
    {
        $str = str_replace(
            ['\\n', '\\r', '\\t', '\\\\', '\\(', '\\)'],
            ["\n", "\r", "\t", '\\', '(', ')'],
            $str
        );

        $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
            return chr(octdec($m[1]));
        }, $str);

        return $str;
    }

    /**
     * Decode hex string
     */
    private function decodeHexString(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex);
        return hex2bin($hex) ?: '';
    }

    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }
    }
}
