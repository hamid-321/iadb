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

    public function albumAutofillAction(?string $albumName, ?string $artistName): ?array
    {
        try
        {
            //request 1: lookup the album by name and artist to get the MBID
            //using lucene query style as per musicbrainz docs
            $searchResponse = $this->client->request('GET', 'release', [
                'query' => [
                    'query' => sprintf('artist:"%s" AND release:"%s"', $artistName, $albumName),
                    'fmt' => 'json'
                ]
            ]);

            $searchData = json_decode($searchResponse->getBody()->getContents(), true);

            if (empty($searchData['releases'])) 
            {
                return null;
            }

            $mbid = $searchData['releases'][0]['id'];

            $lookupResponse = $this->client->request('GET', "release/$mbid", [
                'query' => [
                    'inc' => 'recordings+labels+artist-credits',
                    'fmt' => 'json'
                ]
            ]);

            $data = json_decode($lookupResponse->getBody()->getContents(), true);

            $tracks = [];
            foreach ($data['media'][0]['tracks'] ?? [] as $track) 
            {
                $tracks[] = $track['title'];
            }

            $this->logger->info('MusicBrainz API response for album autofill action: {query}: {response}', [
                'query' => $mbid,
                'response' => $data,
            ]);

            return [
                'mbid' => $mbid,
                'title' => $data['title'],
                'artist' => $data['artist-credit'][0]['name'],
                'tracks' => implode("\n", $tracks),
            ];
            
        }
        catch (\Exception $e) 
        {
            $this->logger->error('MusicBrainz API Autofill error: {error}', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}