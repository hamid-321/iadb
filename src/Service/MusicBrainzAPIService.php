<?php

namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class MusicBrainzAPIService
{
    private $client;
    public function __construct(string $userAgent, private readonly LoggerInterface $logger) 
    {
        $this->client = new Client([
            'base_uri' => 'https://musicbrainz.org/ws/2/',
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function albumAction(string $albumName, string $artistName): ?array
    {
        try
        {
            $query1 = sprintf('artist:"%s" AND release:"%s"', $artistName, $albumName);
            //request 1: lookup the album by name and artist to get the MBID
            //using lucene query style as per musicbrainz docs
            $searchResponse = $this->client->request('GET', 'release', [
                'query' => [
                    'query' => $query1,
                    'fmt' => 'json'
                ]
            ]);

            $searchData = json_decode($searchResponse->getBody()->getContents(), true);

            $this->logger->info('MusicBrainz API response for album action search: {query}: {response}', [
                'query' => $query1,
                'response' => $searchData,
            ]);

            if (empty($searchData['releases']))
            {
                return null;
            }

            // extract MBID of top result (most likely to be correct one)
            $mbid = $searchData['releases'][0]['id'];

            // request 2: lookup of specific MBID to get label data and release date
            $lookupResponse = $this->client->request('GET', "release/$mbid", [
                'query' => [
                    'inc' => 'labels',
                    'fmt' => 'json'
                ]
            ]);

            $data = json_decode($lookupResponse->getBody()->getContents(), true);

            $this->logger->info('MusicBrainz API response for album action lookup: {query}: {response}', [
                'query' => $mbid,
                'response' => $data,
            ]);

            return [
                'releaseDate' => $data['date'] ?? 'Unknown',
                'label' => $data['label-info'][0]['label']['name'] ?? 'Unknown',
            ];

        }
        catch (\Exception $e) 
        {
            //return an null value so nothing is displayed if the album isnt found or the response fails
            $this->logger->error('MusicBrainz API error: {error}', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function albumSearchAction(?string $albumName, ?string $artistName): ?array
    {
        if ($albumName === null || $albumName === '' || $artistName === null || $artistName === '')
        {
            return null;
        }

        try
        {
            //find the top 10 results
            $query = sprintf('artist:"%s" AND release:"%s"', $artistName, $albumName);
            $searchResponse = $this->client->request('GET', 'release', [
                'query' => [
                    'query' => $query,
                    'fmt' => 'json',
                    'limit' => 10,
                ]
            ]);

            $searchData = json_decode($searchResponse->getBody()->getContents(), true);

            $this->logger->info('MusicBrainz API album search: {query}: {count} results', [
                'query' => $query,
                'count' => isset($searchData['releases']) ? count($searchData['releases']) : 0,
            ]);

            if (empty($searchData['releases']))
            {
                return null;
            }

            //get the title, artist and release date to show on the drop down
            $results = [];
            foreach ($searchData['releases'] as $release) {
                $artistNameFromRelease = $release['artist-credit'][0]['name'] ?? 'Unknown';
                $results[] = [
                    'mbid' => $release['id'],
                    'title' => $release['title'] ?? 'Unknown',
                    'artist' => $artistNameFromRelease,
                    'date' => $release['date'] ?? null,
                ];
            }

            return $results;
        } 
        catch (\Exception $e)
        {
            $this->logger->error('MusicBrainz API search error: {error}', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function albumAutofillByMbid(string $mbid): ?array
    {
        try {
            $lookupResponse = $this->client->request('GET', "release/{$mbid}", [
                'query' => [
                    'inc' => 'recordings+labels+artist-credits+genres',
                    'fmt' => 'json'
                ]
            ]);
            $data = json_decode($lookupResponse->getBody()->getContents(), true);

            $genre = '';

            $genres = $data['artist-credit'][0]['artist']['genres'] ?? [];

            if (!empty($genres))
            {
                //sort by the most occuring genre
                usort($genres, function ($a, $b)
                {
                    return $b['count'] <=> $a['count'];
                });

                $genre = $genres[0]['name'];

                $genre = ucfirst(mb_strtolower($genre));
            }

            $tracks = [];
            foreach ($data['media'][0]['tracks'] ?? [] as $track) 
            {
                $tracks[] = $track['title'];
            }

            $this->logger->info('MusicBrainz API autofill by MBID: {mbid}', ['mbid' => $mbid]);

            return [
                'mbid' => $mbid,
                'title' => $data['title'] ?? 'Unknown',
                'artist' => $data['artist-credit'][0]['name'] ?? 'Unknown',
                'tracks' => implode("\n", $tracks),
                'genre' => $genre,
            ];
        }
        catch (\Exception $e)
        {
            $this->logger->error('MusicBrainz API Autofill by MBID error: {error}', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}