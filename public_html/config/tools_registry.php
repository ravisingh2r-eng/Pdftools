<?php
/**
 * PDF Tools Registry
 *
 * Central configuration for all PDF tools.
 * Add new tools here to make them available site-wide.
 *
 * Categories:
 * - basic: Core PDF manipulation (merge, split, rotate, etc.)
 * - compress: File size optimization
 * - convert: Format conversion (PDF to/from other formats)
 * - security: Protection, unlock, watermark
 * - edit: Page editing, annotations
 * - advanced: OCR, form filling, etc.
 *
 * @package PDFTools
 */

return [
    // =========================================================================
    // BASIC TOOLS
    // =========================================================================

    'merge-pdf' => [
        'name' => 'Merge PDF',
        'category' => 'basic',
        'icon' => '📑',
        'color' => '#4CAF50',
        'short_description' => 'Combine multiple PDF files into one document.',
        'long_description' => 'Merge two or more PDF files into a single document. Simply upload your files, arrange them in the desired order, and download your merged PDF.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 1,
    ],

    'split-pdf' => [
        'name' => 'Split PDF',
        'category' => 'basic',
        'icon' => '✂️',
        'color' => '#2196F3',
        'short_description' => 'Separate PDF pages into multiple files.',
        'long_description' => 'Extract specific pages or split your PDF into multiple files. Choose to extract all pages, split by range, or select custom page numbers.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 2,
    ],

    'compress-pdf' => [
        'name' => 'Compress PDF',
        'category' => 'compress',
        'icon' => '🗜️',
        'color' => '#FF9800',
        'short_description' => 'Reduce PDF file size while maintaining quality.',
        'long_description' => 'Compress your PDF to reduce file size for email attachments or web uploads. Choose from multiple compression levels.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 3,
    ],

    'rotate-pdf' => [
        'name' => 'Rotate PDF',
        'category' => 'basic',
        'icon' => '🔄',
        'color' => '#9C27B0',
        'short_description' => 'Rotate PDF pages to any angle.',
        'long_description' => 'Rotate all pages in your PDF by 90, 180, or 270 degrees to fix page orientation.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 4,
    ],

    'protect-pdf' => [
        'name' => 'Protect PDF',
        'category' => 'security',
        'icon' => '🔒',
        'color' => '#F44336',
        'short_description' => 'Add password protection to your PDF files.',
        'long_description' => 'Encrypt your PDF with a password to prevent unauthorized access. Set user and owner passwords for different permission levels.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 5,
    ],

    'split-pdf-range' => [
        'name' => 'Split PDF by Range',
        'category' => 'basic',
        'icon' => '✂️',
        'color' => '#2196F3',
        'short_description' => 'Extract specific page ranges from a PDF.',
        'long_description' => 'Split your PDF by specifying page ranges like 1-3, 5-7. Each range becomes a separate PDF file.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 6,
    ],

    'split-pdf-pages' => [
        'name' => 'Split PDF into Pages',
        'category' => 'basic',
        'icon' => '📄',
        'color' => '#00BCD4',
        'short_description' => 'Split PDF into individual single-page files.',
        'long_description' => 'Separate every page of your PDF into its own file. Perfect for extracting all pages at once.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 7,
    ],

    'reorder-pdf' => [
        'name' => 'Reorder PDF Pages',
        'category' => 'edit',
        'icon' => '🔀',
        'color' => '#3F51B5',
        'short_description' => 'Rearrange PDF pages in any order.',
        'long_description' => 'Reorganize pages in your PDF. Move pages around, duplicate them, or create a completely new arrangement.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 8,
    ],

    'delete-pdf-pages' => [
        'name' => 'Delete PDF Pages',
        'category' => 'edit',
        'icon' => '🗑️',
        'color' => '#607D8B',
        'short_description' => 'Remove unwanted pages from your PDF.',
        'long_description' => 'Delete specific pages from your PDF document. Specify individual pages or ranges to remove.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 9,
    ],

    'delete-pages' => [
        'name' => 'Delete Pages',
        'category' => 'edit',
        'icon' => '🗑️',
        'color' => '#607D8B',
        'short_description' => 'Remove unwanted pages from your PDF.',
        'long_description' => 'Delete specific pages from your PDF document. Select which pages to remove and download the cleaned file.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 6,
    ],

    'extract-pages' => [
        'name' => 'Extract Pages',
        'category' => 'basic',
        'icon' => '📤',
        'color' => '#00BCD4',
        'short_description' => 'Extract specific pages from a PDF.',
        'long_description' => 'Pull out specific pages from your PDF to create a new document. Select individual pages or ranges.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 7,
    ],

    'reorder-pages' => [
        'name' => 'Reorder Pages',
        'category' => 'edit',
        'icon' => '🔀',
        'color' => '#3F51B5',
        'short_description' => 'Rearrange PDF pages in any order.',
        'long_description' => 'Drag and drop to rearrange pages in your PDF. Change page order without splitting and merging.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 8,
    ],

    // =========================================================================
    // SECURITY TOOLS
    // =========================================================================

    'unlock-pdf' => [
        'name' => 'Unlock PDF',
        'category' => 'security',
        'icon' => '🔓',
        'color' => '#8BC34A',
        'short_description' => 'Remove password protection from PDF.',
        'long_description' => 'Remove password protection from your PDF if you know the password. Unlock PDFs for easier access.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 9,
    ],

    'add-watermark' => [
        'name' => 'Add Watermark',
        'category' => 'security',
        'icon' => '💧',
        'color' => '#03A9F4',
        'short_description' => 'Add text or image watermark to PDF.',
        'long_description' => 'Add a text or image watermark to your PDF pages. Customize position, opacity, and rotation.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 10,
    ],

    'sign-pdf' => [
        'name' => 'Sign PDF',
        'category' => 'security',
        'icon' => '✍️',
        'color' => '#673AB7',
        'short_description' => 'Add electronic signature to PDF.',
        'long_description' => 'Sign your PDF documents electronically. Draw, type, or upload your signature.',
        'is_active' => false,
        'is_premium' => true,
        'is_featured' => false,
        'sort_order' => 11,
    ],

    // =========================================================================
    // CONVERSION TOOLS
    // =========================================================================

    'pdf-to-jpg' => [
        'name' => 'PDF to JPG',
        'category' => 'convert',
        'icon' => '🖼️',
        'color' => '#E91E63',
        'short_description' => 'Convert PDF pages to JPG images.',
        'long_description' => 'Convert each page of your PDF to a high-quality JPG image. Perfect for presentations and web use.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 12,
    ],

    'jpg-to-pdf' => [
        'name' => 'JPG to PDF',
        'category' => 'convert',
        'icon' => '📸',
        'color' => '#009688',
        'short_description' => 'Convert JPG images to PDF.',
        'long_description' => 'Combine multiple JPG images into a single PDF document. Adjust page size and orientation.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => true,
        'sort_order' => 13,
    ],

    'pdf-to-png' => [
        'name' => 'PDF to PNG',
        'category' => 'convert',
        'icon' => '🎨',
        'color' => '#795548',
        'short_description' => 'Convert PDF pages to PNG images.',
        'long_description' => 'Convert PDF pages to PNG format with transparency support. Ideal for graphics and web design.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 14,
    ],

    'pdf-to-word' => [
        'name' => 'PDF to Word',
        'category' => 'convert',
        'icon' => '📝',
        'color' => '#2196F3',
        'short_description' => 'Convert PDF to editable Word document.',
        'long_description' => 'Convert your PDF to an editable Word document (.docx). Preserve formatting and layout.',
        'is_active' => false,
        'is_premium' => true,
        'is_featured' => true,
        'sort_order' => 15,
    ],

    'word-to-pdf' => [
        'name' => 'Word to PDF',
        'category' => 'convert',
        'icon' => '📄',
        'color' => '#1976D2',
        'short_description' => 'Convert Word document to PDF.',
        'long_description' => 'Convert your Word documents (.doc, .docx) to PDF format. Maintain formatting and fonts.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 16,
    ],

    'pdf-to-excel' => [
        'name' => 'PDF to Excel',
        'category' => 'convert',
        'icon' => '📊',
        'color' => '#4CAF50',
        'short_description' => 'Convert PDF tables to Excel spreadsheet.',
        'long_description' => 'Extract tables from PDF and convert to Excel format (.xlsx). Edit data easily in spreadsheets.',
        'is_active' => false,
        'is_premium' => true,
        'is_featured' => false,
        'sort_order' => 17,
    ],

    'excel-to-pdf' => [
        'name' => 'Excel to PDF',
        'category' => 'convert',
        'icon' => '📈',
        'color' => '#388E3C',
        'short_description' => 'Convert Excel spreadsheet to PDF.',
        'long_description' => 'Convert your Excel files (.xls, .xlsx) to PDF format. Perfect for sharing and printing.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 18,
    ],

    'pdf-to-ppt' => [
        'name' => 'PDF to PPT',
        'category' => 'convert',
        'icon' => '📽️',
        'color' => '#FF5722',
        'short_description' => 'Convert PDF to PowerPoint presentation.',
        'long_description' => 'Convert your PDF to an editable PowerPoint presentation (.pptx). Edit slides easily.',
        'is_active' => false,
        'is_premium' => true,
        'is_featured' => false,
        'sort_order' => 19,
    ],

    'html-to-pdf' => [
        'name' => 'HTML to PDF',
        'category' => 'convert',
        'icon' => '🌐',
        'color' => '#FF9800',
        'short_description' => 'Convert web page to PDF.',
        'long_description' => 'Convert any web page or HTML file to PDF. Capture the full page layout and styling.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 20,
    ],

    // =========================================================================
    // ADVANCED TOOLS
    // =========================================================================

    'ocr-pdf' => [
        'name' => 'OCR PDF',
        'category' => 'advanced',
        'icon' => '🔍',
        'color' => '#9C27B0',
        'short_description' => 'Make scanned PDFs searchable with OCR.',
        'long_description' => 'Use Optical Character Recognition to make scanned documents searchable and selectable.',
        'is_active' => false,
        'is_premium' => true,
        'is_featured' => false,
        'sort_order' => 21,
    ],

    'repair-pdf' => [
        'name' => 'Repair PDF',
        'category' => 'advanced',
        'icon' => '🔧',
        'color' => '#607D8B',
        'short_description' => 'Fix corrupted or damaged PDF files.',
        'long_description' => 'Repair damaged or corrupted PDF files. Recover content from broken documents.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 22,
    ],

    'flatten-pdf' => [
        'name' => 'Flatten PDF',
        'category' => 'advanced',
        'icon' => '📋',
        'color' => '#00BCD4',
        'short_description' => 'Flatten form fields and annotations.',
        'long_description' => 'Flatten form fields, annotations, and layers into a flat PDF. Prevent further editing.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 23,
    ],

    'compare-pdf' => [
        'name' => 'Compare PDF',
        'category' => 'advanced',
        'icon' => '⚖️',
        'color' => '#3F51B5',
        'short_description' => 'Compare two PDF files for differences.',
        'long_description' => 'Compare two PDF documents side by side. Highlight differences in text and images.',
        'is_active' => false,
        'is_premium' => true,
        'is_featured' => false,
        'sort_order' => 24,
    ],

    'add-page-numbers' => [
        'name' => 'Add Page Numbers',
        'category' => 'edit',
        'icon' => '🔢',
        'color' => '#8BC34A',
        'short_description' => 'Add page numbers to your PDF.',
        'long_description' => 'Add customizable page numbers to your PDF. Choose position, format, and starting number.',
        'is_active' => true,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 25,
    ],

    'crop-pdf' => [
        'name' => 'Crop PDF',
        'category' => 'edit',
        'icon' => '✂️',
        'color' => '#FFC107',
        'short_description' => 'Crop PDF pages to remove margins.',
        'long_description' => 'Crop PDF pages to remove unwanted margins or focus on specific content areas.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 26,
    ],

    'resize-pdf' => [
        'name' => 'Resize PDF',
        'category' => 'edit',
        'icon' => '📐',
        'color' => '#E91E63',
        'short_description' => 'Change PDF page size.',
        'long_description' => 'Resize PDF pages to different paper sizes. Convert A4 to Letter, or create custom sizes.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 27,
    ],

    'edit-metadata' => [
        'name' => 'Edit Metadata',
        'category' => 'advanced',
        'icon' => '🏷️',
        'color' => '#795548',
        'short_description' => 'Edit PDF metadata and properties.',
        'long_description' => 'Edit PDF metadata including title, author, subject, and keywords. Update document properties.',
        'is_active' => false,
        'is_premium' => false,
        'is_featured' => false,
        'sort_order' => 28,
    ],
];
