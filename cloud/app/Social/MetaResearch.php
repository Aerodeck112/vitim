<?php

declare(strict_types=1);

namespace App\Social;

use App\Models\SocialConnection;
use App\Models\SocialReference;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Cercetarea paginii de Facebook și a contului de Instagram ale firmei, prin interfața oficială Meta (Graph API),
 * cu codul de acces al paginii dat de firmă. Citește doar: postările publicate, pozele lor și numărul de reacții.
 */
final class MetaResearch
{
    private const API = 'https://graph.facebook.com';

    public function __construct(private readonly MediaLibrary $library) {}

    /** @return array{posts: int, images: int} */
    public function sync(SocialConnection $connection): array
    {
        $config = (array) $connection->config;
        $token = (string) ($config['token'] ?? '');
        $posts = 0;
        $images = 0;
        try {
            $page = $this->get((string) $config['page_id'].'/posts', $token, 'id,message,created_time,permalink_url,full_picture,shares,likes.summary(true).limit(0),comments.summary(true).limit(0)');
            foreach ($page as $post) {
                $posts += $this->remember('facebook', (string) $post['id'], $post['message'] ?? null, $post['permalink_url'] ?? null, isset($post['full_picture']) ? 'photo' : 'status',
                    (int) ($post['likes']['summary']['total_count'] ?? 0) + (int) ($post['comments']['summary']['total_count'] ?? 0) + (int) ($post['shares']['count'] ?? 0), $post['created_time'] ?? null);
                if (! empty($post['full_picture']) && $this->library->fromMeta((string) $post['full_picture'], self::title($post['message'] ?? null))) {
                    $images++;
                }
            }
            if (! empty($config['ig_id'])) {
                foreach ($this->get((string) $config['ig_id'].'/media', $token, 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count') as $media) {
                    $posts += $this->remember('instagram', (string) $media['id'], $media['caption'] ?? null, $media['permalink'] ?? null, strtolower((string) ($media['media_type'] ?? '')),
                        (int) ($media['like_count'] ?? 0) + (int) ($media['comments_count'] ?? 0), $media['timestamp'] ?? null);
                    $picture = ($media['media_type'] ?? '') === 'VIDEO' ? ($media['thumbnail_url'] ?? null) : ($media['media_url'] ?? null);
                    if ($picture && $this->library->fromMeta((string) $picture, self::title($media['caption'] ?? null))) {
                        $images++;
                    }
                }
            }
        } catch (MetaError $e) {
            $connection->forceFill(['status' => 'error', 'last_error' => Str::limit($e->getMessage(), 480)])->save();

            throw $e;
        }
        $connection->forceFill(['status' => 'ok', 'last_error' => null, 'synced_at' => now()])->save();

        return ['posts' => $posts, 'images' => $images];
    }

    /** @return list<array<string, mixed>> */
    private function get(string $path, string $token, string $fields): array
    {
        if (! preg_match('#^\d{5,30}/[a-z]+$#', $path)) {
            throw new MetaError('ID-ul paginii sau al contului de Instagram trebuie să fie doar din cifre.');
        }
        try {
            // codul de acces merge în antet, nu în adresă, ca să nu ajungă în loguri
            $response = Http::timeout(20)->connectTimeout(6)->withToken($token)->acceptJson()->get(self::API.'/'.$path, ['fields' => $fields, 'limit' => 50]);
        } catch (ConnectionException) {
            throw new MetaError('Meta nu a răspuns. Încearcă din nou peste câteva minute.');
        }
        if (! $response->successful()) {
            throw new MetaError('Meta a refuzat cererea: '.Str::limit((string) ($response->json('error.message') ?? 'HTTP '.$response->status()), 300));
        }

        return array_values(array_filter((array) $response->json('data', []), 'is_array'));
    }

    private function remember(string $network, string $id, ?string $message, ?string $permalink, string $type, int $engagement, ?string $at): int
    {
        if ($id === '' || strlen($id) > 80) {
            return 0;
        }
        $reference = SocialReference::query()->firstOrNew(['network' => $network, 'external_id' => $id]);
        $new = ! $reference->exists;
        $reference->fill(['message' => $message !== null ? Str::limit($message, 3000, '') : null, 'permalink' => $permalink !== null ? Str::limit($permalink, 490, '') : null,
            'media_type' => Str::limit($type, 20, ''), 'engagement' => $engagement, 'posted_at' => $at ? Carbon::parse($at) : null])->save();

        return $new ? 1 : 0;
    }

    private static function title(?string $message): ?string
    {
        return $message !== null && trim($message) !== '' ? Str::limit(trim((string) preg_replace('/\s+/', ' ', $message)), 120) : null;
    }
}
