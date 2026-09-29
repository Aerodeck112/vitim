<?php
declare(strict_types=1);

use App\Core\DB;

return function (): void {
    DB::createTable('settings', [
        'skey' => 'string',
        'svalue' => 'text',
    ], [], [['skey']]);

    DB::createTable('users', [
        'id' => 'id',
        'name' => 'string',
        'email' => 'string',
        'password_hash' => 'string',
        'role' => 'short=admin',
        'totp_secret' => 'string?',
        'reset_token' => 'short?',
        'reset_expires' => 'datetime?',
        'active' => 'bool=1',
        'last_login_at' => 'datetime?',
        'last_login_ip' => 'short?',
        'created_at' => 'datetime?',
    ], [], [['email']]);

    // ---------- Conținut ----------
    $seo = [
        'meta_title' => 'string?',
        'meta_description' => 'text',
        'og_image' => 'string?',
        'canonical' => 'string?',
        'noindex' => 'bool=0',
    ];

    DB::createTable('services', [
        'id' => 'id',
        'slug' => 'string',
        'category' => 'short',
        'title' => 'string',
        'h1' => 'string?',
        'tagline' => 'string?',
        'icon' => 'short?',
        'excerpt' => 'text',
        'body' => 'text',
        'features' => 'json',
        'process' => 'json',
        'faq' => 'json',
        'keywords' => 'text',
        'price_from' => 'string?',
        'onsite' => 'bool=0',
        'featured' => 'bool=0',
        'image' => 'string?',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [['category'], ['published', 'sort']], [['slug']]);

    DB::createTable('locations', [
        'id' => 'id',
        'slug' => 'string',
        'name' => 'string',
        'type' => 'short=judet',
        'parent_id' => 'int?',
        'county_name' => 'string?',
        'intro' => 'text',
        'body' => 'text',
        'faq' => 'json',
        'response_time' => 'string?',
        'lat' => 'string?',
        'lng' => 'string?',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [['parent_id'], ['type']], [['slug']]);

    DB::createTable('pages', [
        'id' => 'id',
        'slug' => 'string',
        'title' => 'string',
        'subtitle' => 'string?',
        'body' => 'text',
        'template' => 'short=default',
        'in_footer' => 'bool=0',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [], [['slug']]);

    DB::createTable('posts', [
        'id' => 'id',
        'slug' => 'string',
        'title' => 'string',
        'excerpt' => 'text',
        'body' => 'text',
        'cover' => 'string?',
        'category' => 'string?',
        'tags' => 'string?',
        'author_id' => 'int?',
        'status' => 'short=draft',
        'published_at' => 'datetime?',
        'faq' => 'json',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [['status', 'published_at']], [['slug']]);

    DB::createTable('testimonials', [
        'id' => 'id',
        'name' => 'string',
        'role' => 'string?',
        'company' => 'string?',
        'text' => 'text',
        'rating' => 'int=5',
        'service_id' => 'int?',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
    ]);

    DB::createTable('projects', [
        'id' => 'id',
        'slug' => 'string',
        'title' => 'string',
        'client' => 'string?',
        'service_id' => 'int?',
        'summary' => 'text',
        'body' => 'text',
        'results' => 'json',
        'cover' => 'string?',
        'sort' => 'int=0',
        'published' => 'bool=1',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ] + $seo, [], [['slug']]);

    DB::createTable('media', [
        'id' => 'id',
        'path' => 'string',
        'original_name' => 'string?',
        'mime' => 'short?',
        'width' => 'int=0',
        'height' => 'int=0',
        'size' => 'int=0',
        'alt' => 'string?',
        'variants' => 'json',
        'created_at' => 'datetime?',
    ]);

    DB::createTable('redirects', [
        'id' => 'id',
        'from_path' => 'string',
        'to_path' => 'string',
        'code' => 'int=301',
        'hits' => 'int=0',
        'last_hit_at' => 'datetime?',
        'created_at' => 'datetime?',
    ], [], [['from_path']]);

    DB::createTable('not_found_log', [
        'id' => 'id',
        'path' => 'string',
        'referer' => 'string?',
        'hits' => 'int=1',
        'last_seen_at' => 'datetime?',
    ], [], [['path']]);

    // ---------- CRM ----------
    DB::createTable('contacts', [
        'id' => 'id',
        'name' => 'string',
        'email' => 'string?',
        'phone' => 'short?',
        'company' => 'string?',
        'cui' => 'short?',
        'position' => 'string?',
        'city' => 'string?',
        'county' => 'string?',
        'address' => 'string?',
        'website' => 'string?',
        'status' => 'short=lead',
        'source' => 'short?',
        'tags' => 'string?',
        'notes' => 'text',
        'owner_id' => 'int?',
        'newsletter' => 'short=none',
        'newsletter_consent_at' => 'datetime?',
        'newsletter_consent_ip' => 'short?',
        'confirm_token' => 'short?',
        'unsub_token' => 'short?',
        'unsubscribed_at' => 'datetime?',
        'last_contact_at' => 'datetime?',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [['status'], ['newsletter'], ['email'], ['unsub_token'], ['confirm_token']]);

    DB::createTable('deals', [
        'id' => 'id',
        'contact_id' => 'int',
        'title' => 'string',
        'service' => 'string?',
        'value' => 'decimal=0',
        'stage' => 'short=nou',
        'source' => 'short?',
        'message' => 'text',
        'utm' => 'json',
        'owner_id' => 'int?',
        'expected_close' => 'datetime?',
        'closed_at' => 'datetime?',
        'sort' => 'int=0',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [['contact_id'], ['stage']]);

    DB::createTable('activities', [
        'id' => 'id',
        'contact_id' => 'int?',
        'deal_id' => 'int?',
        'user_id' => 'int?',
        'type' => 'short=note',
        'body' => 'text',
        'due_at' => 'datetime?',
        'done' => 'bool=0',
        'created_at' => 'datetime?',
    ], [['contact_id'], ['deal_id'], ['type', 'done']]);

    DB::createTable('submissions', [
        'id' => 'id',
        'form' => 'short',
        'contact_id' => 'int?',
        'deal_id' => 'int?',
        'data' => 'json',
        'page' => 'string?',
        'ip' => 'short?',
        'user_agent' => 'string?',
        'spam_score' => 'int=0',
        'read_at' => 'datetime?',
        'created_at' => 'datetime?',
    ], [['form'], ['read_at']]);

    // ---------- Email marketing ----------
    DB::createTable('campaigns', [
        'id' => 'id',
        'name' => 'string',
        'kind' => 'short=newsletter',
        'subject' => 'string',
        'preheader' => 'string?',
        'body' => 'text',
        'audience' => 'json',
        'status' => 'short=draft',
        'scheduled_at' => 'datetime?',
        'started_at' => 'datetime?',
        'finished_at' => 'datetime?',
        'total' => 'int=0',
        'sent' => 'int=0',
        'failed' => 'int=0',
        'opens' => 'int=0',
        'clicks' => 'int=0',
        'unsubs' => 'int=0',
        'created_by' => 'int?',
        'created_at' => 'datetime?',
        'updated_at' => 'datetime?',
    ], [['status']]);

    DB::createTable('campaign_recipients', [
        'id' => 'id',
        'campaign_id' => 'int',
        'contact_id' => 'int?',
        'email' => 'string',
        'name' => 'string?',
        'token' => 'short',
        'status' => 'short=queued',
        'error' => 'string?',
        'sent_at' => 'datetime?',
        'opened_at' => 'datetime?',
        'open_count' => 'int=0',
        'clicked_at' => 'datetime?',
        'click_count' => 'int=0',
    ], [['campaign_id', 'status'], ['token']]);

    DB::createTable('campaign_links', [
        'id' => 'id',
        'campaign_id' => 'int',
        'url' => 'text',
        'clicks' => 'int=0',
    ], [['campaign_id']]);

    DB::createTable('email_log', [
        'id' => 'id',
        'to_email' => 'string',
        'subject' => 'string?',
        'kind' => 'short?',
        'status' => 'short=sent',
        'error' => 'text',
        'created_at' => 'datetime?',
    ], [['created_at']]);
};
