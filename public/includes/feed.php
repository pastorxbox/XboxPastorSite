<?php

declare(strict_types=1);

function feed_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function feed_rfc822_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return date('r');
    }

    $timestamp = strtotime($datetime);

    return $timestamp === false ? date('r') : date('r', $timestamp);
}

function feed_render_rss(array $config, array $posts): string
{
    $siteName = (string) ($config['site_name'] ?? 'Pastor Xbox');
    $siteUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    $feedUrl = $siteUrl . '/rss.xml';
    $description = 'Notícias e análises sobre Xbox.';

    $lastBuild = '';
    if ($posts !== [] && isset($posts[0]['published_at'])) {
        $lastBuild = feed_rfc822_date((string) $posts[0]['published_at']);
    }

    $itemsXml = '';
    foreach ($posts as $post) {
        $id = (int) $post['id'];
        $title = feed_xml_escape((string) $post['title']);
        $link = feed_xml_escape(site_post_url($config, $id));
        $pubDate = feed_rfc822_date(isset($post['published_at']) ? (string) $post['published_at'] : null);
        $body = (string) $post['body'];
        $itemsXml .= <<<XML
    <item>
      <title>{$title}</title>
      <link>{$link}</link>
      <guid isPermaLink="true">{$link}</guid>
      <pubDate>{$pubDate}</pubDate>
      <description><![CDATA[{$body}]]></description>
    </item>

XML;
    }

    $channelTitle = feed_xml_escape($siteName);
    $channelLink = feed_xml_escape($siteUrl . '/');
    $channelDescription = feed_xml_escape($description);
    $channelFeedUrl = feed_xml_escape($feedUrl);
    $channelLastBuild = feed_xml_escape($lastBuild);

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>{$channelTitle}</title>
    <link>{$channelLink}</link>
    <description>{$channelDescription}</description>
    <language>pt-BR</language>
    <lastBuildDate>{$channelLastBuild}</lastBuildDate>
    <atom:link href="{$channelFeedUrl}" rel="self" type="application/rss+xml"/>
{$itemsXml}  </channel>
</rss>
XML;
}
