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

/**
 * @param array{
 *   title: string,
 *   description: string,
 *   feed_url: string,
 *   channel_link: string,
 * } $channel
 * @param list<array{title: string, link: string, guid: string, pub_date: string, description: string}> $items
 */
function feed_render_channel(array $channel, array $items): string
{
    $lastBuild = $items !== [] ? feed_rfc822_date($items[0]['pub_date']) : feed_rfc822_date(null);

    $itemsXml = '';
    foreach ($items as $item) {
        $title = feed_xml_escape($item['title']);
        $link = feed_xml_escape($item['link']);
        $guid = feed_xml_escape($item['guid']);
        $pubDate = feed_xml_escape(feed_rfc822_date($item['pub_date']));
        $body = $item['description'];
        $itemsXml .= <<<XML
    <item>
      <title>{$title}</title>
      <link>{$link}</link>
      <guid isPermaLink="false">{$guid}</guid>
      <pubDate>{$pubDate}</pubDate>
      <description><![CDATA[{$body}]]></description>
    </item>

XML;
    }

    $channelTitle = feed_xml_escape($channel['title']);
    $channelLink = feed_xml_escape($channel['channel_link']);
    $channelDescription = feed_xml_escape($channel['description']);
    $channelFeedUrl = feed_xml_escape($channel['feed_url']);
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

/**
 * @param list<array<string, mixed>> $posts
 */
function feed_render_rss(array $config, array $posts): string
{
    $siteName = (string) ($config['site_name'] ?? 'Pastor Xbox');
    $siteUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/');

    $items = [];
    foreach ($posts as $post) {
        $id = (int) $post['id'];
        $link = site_post_url($config, $id);
        $items[] = [
            'title' => (string) $post['title'],
            'link' => $link,
            'guid' => $link,
            'pub_date' => isset($post['published_at']) ? (string) $post['published_at'] : '',
            'description' => (string) ($post['body'] ?? ''),
        ];
    }

    return feed_render_channel(
        [
            'title' => $siteName . ' — Notícias',
            'description' => 'Notícias e análises sobre Xbox.',
            'feed_url' => $siteUrl . '/rss.xml',
            'channel_link' => $siteUrl . '/',
        ],
        $items
    );
}

/**
 * @param list<array{title: string, link: string, guid: string, pub_date: string, description: string}> $items
 */
function feed_render_calendar_rss(array $config, array $items): string
{
    $siteName = (string) ($config['site_name'] ?? 'Pastor Xbox');
    $siteUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/');

    return feed_render_channel(
        [
            'title' => $siteName . ' — Calendário',
            'description' => 'Lançamentos Xbox, eventos e fatos históricos.',
            'feed_url' => $siteUrl . '/calendario/rss.xml',
            'channel_link' => $siteUrl . '/calendario',
        ],
        $items
    );
}
