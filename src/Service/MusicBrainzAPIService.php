<?php

namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

class MusicBrainzAPIService
{
    private $client;
    public function __construct(string $userAgent) 
    {
        $this->client = new Client([
            'base_uri' => 'https://musicbrainz.org/ws/2/',
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function albumAction(string $albumName, string $artistName): array
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
                return [
                    'releaseDate' => 'Unknown',
                    'label' => 'Unknown',
                ];
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

            return [
                'releaseDate' => $data['date'] ?? 'Unknown',
                'label' => $data['label-info'][0]['label']['name'] ?? 'Independent',
            ];

        }
        catch (\Exception $e) 
        {
            //return a default response so something is displayed if the album isnt found or the response fails
            return [
                'releaseDate' => 'Unknown',
                'label' => 'Unknown',
            ];
        }
    }

    public function albumAutofillAction(?string $albumName, ?string $artistName): array
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
                return [];
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

            return [
                'mbid' => $mbid,
                'title' => $data['title'],
                'artist' => $data['artist-credit'][0]['name'],
                'tracks' => implode("\n", $tracks),
            ];
            
        }
        catch (\Exception $e) 
        {
            return [];
        }
    }
}