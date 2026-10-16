<?php
/**
 * Aikaaa CRM Global Spelling Updater
 * Upload this file to your server's root directory and run it via your browser:
 * e.g., https://yourdomain.com/update_spelling.php
 * 
 * NOTE: Delete this file after running it!
 */

// Set time limit for large directories
set_time_limit(300);

echo "<h1>Aikaaa CRM Global Spelling Updater</h1>";
echo "<pre>";

$rootDir = __DIR__;
$extensions = ['php', 'tsx', 'ts', 'json', 'md', 'sql', 'html', 'js', 'css'];

$totalFiles = 0;
$updatedFiles = 0;
$totalReplacements = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
        
        if (in_array($ext, $extensions)) {
            $totalFiles++;
            $filepath = $file->getPathname();
            
            // Skip this exact updater file to prevent self-modification corruption
            if ($filepath === __FILE__) {
                continue;
            }

            $content = file_get_contents($filepath);
            $originalContent = $content;

            // 1. Replace Title Case (Aikaa -> Aikaaa) ONLY if not already Aikaaa
            $content = preg_replace('/Aikaa(?!a)/', 'Aikaaa', $content);

            // 2. Replace Lower Case (aikaa -> aikaaa) ONLY if not already aikaaa
            $content = preg_replace('/aikaa(?!a)/', 'aikaaa', $content);

            // 3. Replace All Caps (AIKAA -> AIKAAA) ONLY if not already AIKAAA
            $content = preg_replace('/AIKAA(?!A)/', 'AIKAAA', $content);

            if ($content !== $originalContent) {
                file_put_contents($filepath, $content);
                $updatedFiles++;
                echo "Updated: " . str_replace($rootDir, '', $filepath) . "\n";
            }
        }
    }
}

echo "\n========================================\n";
echo "Scan Complete!\n";
echo "Total files scanned: $totalFiles\n";
echo "Files updated: $updatedFiles\n";
echo "========================================\n";
echo "</pre>";
echo "<p style='color: red; font-weight: bold;'>IMPORTANT: Please delete this file (update_spelling.php) from your server for security reasons now that the update is complete.</p>";
?>
