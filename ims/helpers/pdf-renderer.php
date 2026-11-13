<?php

if (!function_exists('ims_pdf_chrome_path')) {
    function ims_pdf_chrome_path(): string
    {
        $candidates = [
            getenv('IMS_CHROME_PATH') ?: '',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            getenv('LOCALAPPDATA') ? getenv('LOCALAPPDATA') . '\\Google\\Chrome\\Application\\chrome.exe' : '',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
            '/usr/bin/microsoft-edge',
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return '';
    }
}

if (!function_exists('ims_pdf_filename')) {
    function ims_pdf_filename(string $filename): string
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($filename));
        $filename = trim((string) $filename, '.-');
        return $filename !== '' ? $filename : 'document.pdf';
    }
}

if (!function_exists('ims_pdf_asset_url')) {
    function ims_pdf_asset_url(string $publicPath): string
    {
        $publicPath = ltrim($publicPath, '/');
        $webPath = (defined('BASE_URL') ? BASE_URL : '') . '/' . $publicPath;
        if ((int) ($_GET['pdf'] ?? 0) !== 1 || !defined('ROOT_PATH')) {
            return $webPath;
        }

        $relativePath = str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
        $root = rtrim(ROOT_PATH, '/\\');
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . $relativePath,
            $root . DIRECTORY_SEPARATOR . $relativePath,
        ];

        foreach ($candidates as $localPath) {
            if (!is_file($localPath)) {
                continue;
            }

            $data = file_get_contents($localPath);
            if ($data === false) {
                continue;
            }

            $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $mime = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
            ][$extension] ?? 'application/octet-stream';

            return 'data:' . $mime . ';base64,' . base64_encode($data);
        }

        return $webPath;
    }
}

if (!function_exists('ims_pdf_remove_path')) {
    function ims_pdf_remove_path(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            @rmdir($path);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            ims_pdf_remove_path($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}

if (!function_exists('ims_output_pdf_or_html')) {
    function ims_output_pdf_or_html(string $html, string $filename): void
    {
        $wantsPdf = (int) ($_GET['pdf'] ?? 0) === 1;
        if (!$wantsPdf) {
            echo $html;
            return;
        }

        $chrome = ims_pdf_chrome_path();
        if ($chrome === '') {
            echo $html;
            return;
        }

        $tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ims_pdf_' . bin2hex(random_bytes(8));
        $htmlFile = $tmpBase . '.html';
        $pdfFile = $tmpBase . '.pdf';
        $profileDir = $tmpBase . '_profile';

        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0777, true);
        }

        file_put_contents($htmlFile, $html);

        $command = '"' . $chrome . '"'
            . ' --headless=new'
            . ' --disable-gpu'
            . ' --disable-dev-shm-usage'
            . ' --log-level=3'
            . ' --no-sandbox'
            . ' --allow-file-access-from-files'
            . ' --no-pdf-header-footer'
            . ' --print-to-pdf-no-header'
            . ' --run-all-compositor-stages-before-draw'
            . ' --virtual-time-budget=1000'
            . ' --user-data-dir=' . escapeshellarg($profileDir)
            . ' --print-to-pdf=' . escapeshellarg($pdfFile)
            . ' ' . escapeshellarg('file:///' . str_replace('\\', '/', $htmlFile))
            . (DIRECTORY_SEPARATOR === '\\' ? ' 2>NUL' : ' 2>/dev/null');

        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($pdfFile) || filesize($pdfFile) <= 0) {
            @unlink($htmlFile);
            @unlink($pdfFile);
            ims_pdf_remove_path($profileDir);
            echo $html;
            return;
        }

        $safeFilename = ims_pdf_filename($filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        header('Content-Length: ' . filesize($pdfFile));
        header('X-Content-Type-Options: nosniff');
        readfile($pdfFile);

        @unlink($htmlFile);
        @unlink($pdfFile);
        ims_pdf_remove_path($profileDir);
    }
}
