<?php

namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class YoutubeAPIService
{
    private $client;
    private $apiKey;
    public function __construct(string $apiKey, private readonly LoggerInterface $logger) 
    {
        $this->client = new Client(['base_uri' => 'https://www.googleapis.com/youtube/v3/']);
        $this->apiKey = $apiKey;
    }

    public function searchVideos(string $albumName, string $artistName, array $trackTitles = []): ?string
    {
        $query = sprintf('"%s" Album "%s" official music video', $artistName, $albumName);
        try
        {
            //if we have a tracklist, get up to that number of results
            $maxResults = count($trackTitles) > 0 ? 10 : 1;

            $response = $this->client->request('GET', 'search', [
                'query' => [
                    'part' => 'snippet',
                    'q' => $query,
                    'maxResults' => $maxResults,
                    'order' => 'relevance',
                    'type' => 'video',
                    'key' => $this->apiKey,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $items = $data['items'] ?? [];

            //if no tracklist, get the first most relevant result (no tracklist to match to)
            if (count($trackTitles) === 0) 
            {
                $videoId = $items[0]['id']['videoId'] ?? null;

                $this->logger->info('YouTube API response for {query}: {response}', [
                    'query' => $query,
                    'response' => $videoId,
                ]);

                return $videoId;
            }

            //if we have a tracklist now, cyheck the video titles against the tracklist and return the first match
            foreach ($items as $item) 
            {
                $videoId = $item['id']['videoId'] ?? null;
                $videoTitle = $item['snippet']['title'] ?? '';
                if ($videoId && $this->checkValidVideo($videoTitle, $trackTitles)) 
                {
                    $this->logger->info('YouTube API matched video for {query}: {response}', [
                        'query' => $query,
                        'response' => $videoId,
                    ]);

                    return $videoId;
                }
            }

            $this->logger->info('YouTube API found no matching video for {query}', [
                'query' => $query
            ]);

            return null;
        }
        catch (\Exception $e)
        {
            $this->logger->error('YouTube API error: {error}', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function checkValidVideo(string $videoTitle, array $trackTitles): bool
    {
        $checkVideoTitle = strtolower($videoTitle);
        foreach ($trackTitles as $track) 
        {
            if (str_contains($checkVideoTitle, strtolower($track))) 
            {
                return true;
            }
        }
        return false;
    }
}