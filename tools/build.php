<?php
// tools/build.php

$sourceDir = realpath(__DIR__ . '/..');
$distDir = $sourceDir . '/dist';
$wwwDir = $distDir . '/www';

echo "Building SmartShop Portable...\n";
echo "Source: $sourceDir\n";
echo "Destination: $distDir\n";

// Clean dist directory if it exists
if (is_dir($distDir)) {
    echo "Cleaning existing dist directory...\n";
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($distDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir($distDir);
}

// Create directories
if (!mkdir($wwwDir, 0777, true)) {
    die("Failed to create dist directory.\n");
}

// Setup iterator
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$fileCount = 0;

/**
 * Obfuscates PHP content using Deflate + Base64 + Rotation
 * This makes the code unreadable to casual inspection.
 */
function obfuscate_php_content($content) {
    // 1. Remove open/close tags to get the body
    $content = trim($content);
    // Remove leading < ?php or < ?
    // We break the string to avoid parser confusion and regex escaping issues
    $content = preg_replace("/^<" . "\\?php\s*/i", "", $content);
    $content = preg_replace("/^<" . "\\?\s*/", "", $content);
    // Remove trailing ? >
    $content = preg_replace("/\\?" . ">$/", "", $content);

    // 2. Compress/Obfuscate
    // gzdeflate compresses and hides the string structure
    $compressed = gzdeflate($content, 9);
    // Base64 encode first to get safe chars
    $encoded = base64_encode($compressed);
    // str_rot13 adds a simple rotation layer on the base64 string
    $rotated = str_rot13($encoded);

    // 3. Generate Loader Stub
    // Uses variable functions to hide 'base64_decode', 'str_rot13', 'gzinflate' slightly
    // Note: eval is a language construct, cannot be variable-ized easily without create_function (deprecated)
    // We will use a direct but slightly messy loader.
    
    $v1 = 's' . 'tr_r' . 'ot1' . '3'; // str_rot13
    $v2 = 'b' . 'ase6' . '4_de' . 'code'; // base64_decode
    $v3 = 'g' . 'zinf' . 'late'; // gzinflate
    
    // We construct a PHP file that reconstructs the code and evals it
    // We add error suppression @ to avoid warnings on some systems
    // Using double quotes and concatenation to avoid parsing errors
    $stub = "<" . "?php ";
    $stub .= "/* SMART SHOP SECURE BOOT */ ";
    $stub .= "\$x='";
    $stub .= $rotated;
    $stub .= "';";
    $stub .= "return eval(@$v3(@$v2(@$v1(\$x))));";
    $stub .= " ?" . ">";

    return $stub;
}

foreach ($iterator as $item) {
    // Calculate relative path
    $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
    
    // Skip excluded files/directories
    // Exclude hidden files, tools, dist, and git
    if (strpos($relativePath, '.') === 0 && strpos($relativePath, '.htaccess') === false) continue; // Skip .git, .vscode, etc (but keep .htaccess if any)
    if (strpos($relativePath, 'dist') === 0) continue;
    if (strpos($relativePath, 'tools') === 0) continue;
    if (strpos($relativePath, 'launcher') === 0) continue; // Skip launcher (handled separately)
    if (strpos($relativePath, 'node_modules') === 0) continue;
    if (strpos($relativePath, 'tests') === 0) continue;
    if ($relativePath == 'README.md' || $relativePath == 'AGENTS.md') continue;

    $destPath = $wwwDir . '/' . $relativePath;

    if ($item->isDir()) {
        if (!is_dir($destPath)) {
            mkdir($destPath, 0777, true);
        }
    } else {
        $ext = pathinfo($item->getFilename(), PATHINFO_EXTENSION);
        
        if ($ext === 'php') {
            // Read content
            // We use php_strip_whitespace first to remove comments and whitespace from source
            $content = php_strip_whitespace($item->getRealPath());
            
            // Check if it's a pure PHP file (starts with <?)
            // We only obfuscate pure PHP files to avoid breaking mixed HTML/PHP views with eval()
            if (stripos(trim($content), '<' . '?') === 0) {
                // Apply Advanced Obfuscation
                $content = obfuscate_php_content($content);
                // echo "Obfuscated: $relativePath\n";
            } else {
                // Mixed content (HTML starting files): Do NOT obfuscate with eval().
                // Just keep the stripped content (comments removed by php_strip_whitespace above).
                // This protects logic in mixed files slightly but ensures they render correctly.
            }
            
            file_put_contents($destPath, $content);
        } else {
            // Copy other files as is
            copy($item->getRealPath(), $destPath);
        }
        $fileCount++;
    }
}

echo "Build complete! Processed $fileCount files.\n";

// Copy launcher scripts
echo "Copying launcher scripts...\n";
if (file_exists($sourceDir . '/launcher/SmartShop.bat')) {
    copy($sourceDir . '/launcher/SmartShop.bat', $distDir . '/SmartShop.bat');
    echo "Copied SmartShop.bat to dist/\n";
}
if (file_exists($sourceDir . '/launcher/stop.bat')) {
    copy($sourceDir . '/launcher/stop.bat', $distDir . '/stop.bat');
    echo "Copied stop.bat to dist/\n";
}
if (file_exists($sourceDir . '/launcher/db_console.bat')) {
    copy($sourceDir . '/launcher/db_console.bat', $distDir . '/db_console.bat');
    echo "Copied db_console.bat to dist/\n";
}

// Copy C# Launcher Source and Build Script
if (file_exists($sourceDir . '/launcher/SmartShopLauncher.cs')) {
    copy($sourceDir . '/launcher/SmartShopLauncher.cs', $distDir . '/SmartShopLauncher.cs');
    echo "Copied SmartShopLauncher.cs to dist/\n";
}
if (file_exists($sourceDir . '/launcher/compile_launcher.bat')) {
    copy($sourceDir . '/launcher/compile_launcher.bat', $distDir . '/compile_launcher.bat');
    echo "Copied compile_launcher.bat to dist/\n";
}

// Copy Required List
if (file_exists($sourceDir . '/launcher/REQUIRED.md')) {
    copy($sourceDir . '/launcher/REQUIRED.md', $distDir . '/required.txt');
    echo "Copied REQUIRED.md to dist/required.txt\n";
}

// Copy README
if (file_exists($sourceDir . '/launcher/README_AR.md')) {
    copy($sourceDir . '/launcher/README_AR.md', $distDir . '/README_AR.txt');
    echo "Copied README_AR.md to dist/README_AR.txt\n";
}

echo "Files are located in: $wwwDir\n";
?>
