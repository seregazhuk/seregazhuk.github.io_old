<?php

$postsDir = '_posts/';
$tagsDir = 'tag/';
$tags = [];

foreach (new DirectoryIterator($postsDir) as $fileInfo) {
    if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getExtension() !== 'md') {
        continue;
    }

    $lines = file($fileInfo->getRealPath());
    $crawl = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if($line === '---') {
            $crawl = !$crawl;
            if(!$crawl) {
                break;
            }
        }

        if($crawl) {

            if(strpos($line, 'tags:') === 0) {
                $line = str_replace(['tags:', ']', '['], '', $line);
                $tags = array_merge($tags, explode(',', $line));
                $crawl = false;
            }
        }
    }
}

$tags = array_map('trim', array_unique($tags));

foreach ($tags as $tag) {
    $tagFileName = $tagsDir . str_replace(' ', '-', strtolower($tag)) . '.md';
    $file = fopen($tagFileName, 'w');
    $content = [
        '---',
        'layout: tag',
        'title: "Posts For Tag: ' . $tag . '"',
        'tag: ' . $tag,
        'robots: noindex',
        'sitemap: false',
        '---',
    ];
    fwrite($file, implode("\n", $content));
    fclose($file);
}

echo 'Tags generated, total: ' . count($tags) . "\n";
