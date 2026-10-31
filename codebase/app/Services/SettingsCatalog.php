<?php
namespace EpaperSaas\Services;

final class SettingsCatalog
{
    public static function groups(): array
    {
        return [
            'appearance' => [
                'label' => 'Appearance',
                'description' => 'Independent Frontend, Reader, Admin and Authentication themes, public light/dark policy and density.',
                'fields' => [
                    'appearance.frontend_theme' => ['Frontend theme','select','Default',['Default','Newsstand Modern','Editorial Portal','Classic Newspaper','Digital Magazine']],
                    'appearance.admin_theme' => ['Admin theme','select','Publisher Pro',['Publisher Pro','Executive Slate','Midnight Desk','Default']],
                    'appearance.reader_theme' => ['Reader theme','select','Newsstand Replica',['Newsstand Replica','Editorial Luxe','Metro Pulse','Heritage Press','Apple Editorial','Default']],
                    'appearance.auth_theme' => ['Authentication theme','select','Split Editorial',['Split Editorial','Gradient Focus','Minimal Access','Default']],
                    'appearance.public_color_mode' => ['Public / Reader color default','select','light',['light','system','dark']],
                    'appearance.public_color_switcher' => ['Public / Reader light-dark switcher','select','on',['on','off']],
                    'appearance.density' => ['Admin density','select','Comfortable',['Comfortable','Compact']],
                ],
            ],
            'brand' => [
                'label' => 'Brand & identity',
                'description' => 'Tenant-level white-label identity used across the public site, Reader, account and admin.',
                'fields' => [
                    'brand.site_name' => ['Website name','text','ePaper SaaS',null],
                    'brand.tagline' => ['Tagline','text','Digital newspaper',null],
                    'brand.logo_path' => ['Logo path / URL','text','',null],
                    'brand.favicon_path' => ['Favicon path / URL','text','',null],
                    'brand.accent' => ['Accent color','color','#2563eb',null],
                    'brand.footer_text' => ['Footer text','text','',null],
                ],
            ],
            'seo' => [
                'label' => 'SEO & discovery',
                'description' => 'Search, News, social and organization metadata. Semantic article/author/topic pages remain authoritative where supplied.',
                'fields' => [
                    'seo.title_suffix' => ['Title suffix','text','ePaper',null],
                    'seo.description' => ['Default meta description','textarea','',null],
                    'seo.robots' => ['Robots policy','select','index,follow,max-image-preview:large',['index,follow,max-image-preview:large','index,follow','noindex,nofollow']],
                    'seo.social_image' => ['Default social image','text','',null],
                    'seo.default_language' => ['Default language / locale','text','en-IN',null],
                    'seo.news_publication_name' => ['Google News publication name','text','',null],
                    'seo.organization_name' => ['Organization name','text','',null],
                    'seo.organization_description' => ['Organization description','textarea','',null],
                    'seo.organization_logo' => ['Organization logo URL','text','',null],
                    'seo.organization_email' => ['Public contact email','text','',null],
                    'seo.organization_phone' => ['Public contact phone','text','',null],
                    'seo.organization_street' => ['Street address','text','',null],
                    'seo.organization_locality' => ['Locality / city','text','',null],
                    'seo.organization_region' => ['Region / state','text','',null],
                    'seo.organization_postal_code' => ['Postal code','text','',null],
                    'seo.organization_country' => ['Country code','text','IN',null],
                    'seo.same_as' => ['Official profile URLs — one per line','textarea','',null],
                ],
            ],
            'trust' => [
                'label' => 'Editorial Trust',
                'description' => 'Publisher-authored trust and accountability policies surfaced to readers, search systems and AI discovery systems.',
                'fields' => [
                    'trust.masthead' => ['Masthead / newsroom leadership','textarea','',null],
                    'trust.editorial_standards' => ['Editorial standards','textarea','',null],
                    'trust.corrections_policy' => ['Corrections policy','textarea','',null],
                    'trust.fact_checking_policy' => ['Fact-checking policy','textarea','',null],
                    'trust.ethics_policy' => ['Ethics policy','textarea','',null],
                    'trust.ai_policy' => ['AI-use policy','textarea','',null],
                    'trust.advertising_policy' => ['Advertising / sponsored-content policy','textarea','',null],
                    'trust.ownership' => ['Ownership disclosure','textarea','',null],
                    'trust.contact_newsroom' => ['Contact the newsroom','textarea','',null],
                ],
            ],
            'access' => [
                'label' => 'Reader access',
                'description' => 'Control local reader verification, temporary device access and trusted-app approval for desktop sign-in.',
                'fields' => [
                    'reader_auth.sms' => ['SMS verification','select','on',['on','off']],
                    'reader_auth.whatsapp' => ['WhatsApp verification','select','off',['on','off']],
                    'reader_auth.sna' => ['Silent Network Auth (SNA)','select','off',['on','off']],
                    'reader_auth.device_fallback' => ['Temporary device account fallback','select','on',['on','off']],
                    'reader_auth.device_ttl_days' => ['Temporary account inactivity expiry (days)','number','30',null],
                    'reader_auth.device_limit_per_ip_day' => ['New temporary accounts / IP / day','number','5',null],
                    'reader_auth.merge_on_verify' => ['Merge temporary reader data after mobile verification','select','on',['on','off']],
                    'reader_auth.desktop_login' => ['Desktop reader login','select','on',['on','off']],
                    'reader_auth.desktop_app_approval' => ['Trusted mobile app approval','select','on',['on','off']],
                    'reader_auth.desktop_sms_fallback' => ['Desktop SMS fallback','select','on',['on','off']],
                    'reader_auth.desktop_whatsapp_fallback' => ['Desktop WhatsApp fallback','select','on',['on','off']],
                    'reader_auth.desktop_challenge_minutes' => ['App approval expiry (minutes)','number','3',null],
                    'reader_auth.desktop_session_days' => ['Desktop session lifetime (days)','number','30',null],
                    'reader_auth.mobile_session_days' => ['Mobile app session lifetime (days)','number','365',null],
                ],
            ],
            'reader' => [
                'label' => 'Reader',
                'description' => 'City ePaper parity controls for fit, zoom, page navigation, edition switching, article mode and offline behavior.',
                'fields' => [
                    'reader.theme' => ['Reader theme','select','Newsstand Replica',['Newsstand Replica','Editorial Luxe','Metro Pulse','Heritage Press','Apple Editorial','Default']],
                    'reader.default_fit' => ['Default fit','select','page',['page','width']],
                    'reader.default_view' => ['Default reader view','select','flip',['flip','page','continuous']],
                    'reader.flip_desktop_spread' => ['Flip: desktop two-page spread','select','on',['on','off']],
                    'reader.flip_tablet_spread' => ['Flip: tablet landscape spread','select','on',['on','off']],
                    'reader.flip_shadow' => ['Flip: realistic paper shadow','select','on',['on','off']],
                    'reader.flip_duration_ms' => ['Flip animation duration (ms)','number','420',null],
                    'reader.flip_low_power_fallback' => ['Flip: low-power / reduced-motion fallback','select','on',['on','off']],
                    'reader.scroll_zoom_default' => ['Mouse scroll zoom default','select','off',['off','on']],
                    'reader.smart_article' => ['Smart Article','select','on',['on','off']],
                    'reader.prefetch' => ['Adjacent-page prefetch','select','on',['on','off']],
                    'reader.resume' => ['Resume reading','select','on',['on','off']],
                    'reader.edition_date_switcher' => ['Enable edition / back-date navigation','select','on',['on','off']],
                    'reader.edition_navigation' => ['Edition navigation style','select','dropdown',['dropdown','strip','off']],
                    'reader.edition_history_limit' => ['Back editions available in dropdown','number','365',null],
                    'reader.offline' => ['Offline reading UI','select','on',['on','off']],
                    'reader.offline_window_days' => ['Offline rolling window (days)','number','7',null],
                    'reader.offline_edition_limit' => ['Offline editions per device','number','7',null],
                ],
            ],
            'processing' => [
                'label' => 'Processing',
                'description' => 'PDF rendering, generated media, OCR and recovery defaults. RabbitMQ/Supervisor transport remains configured under Infrastructure.',
                'fields' => [
                    'processing.pdf_dpi' => ['PDF render DPI','number','180',null],
                    'processing.preview_width' => ['Preview width','number','1280',null],
                    'processing.thumbnail_width' => ['Thumbnail width','number','360',null],
                    'processing.tile_size' => ['Deep Zoom tile size','number','512',null],
                    'processing.highres_quality' => ['High-resolution WebP quality','number','90',null],
                    'processing.preview_quality' => ['Preview WebP quality','number','82',null],
                    'processing.thumbnail_quality' => ['Thumbnail WebP quality','number','78',null],
                    'processing.tile_quality' => ['Deep Zoom WebP quality','number','82',null],
                    'processing.ocr_languages' => ['OCR languages / auto profile','text','auto',null],
                    'processing.ocr_timeout_seconds' => ['OCR timeout / page (seconds)','number','300',null],
                    'processing.auto_language' => ['Use edition language OCR profile','select','on',['on','off']],
                    'processing.recovery_manual_smart' => ['Smart corrections preselected for manual Reprocess','select','on',['on','off']],
                    'processing.recovery_auto_rescue' => ['Automatic rescue after normal retries are exhausted','select','off',['off','on']],
                    'processing.recovery_max_rescues' => ['Maximum automatic rescue passes','number','1',null],
                    'processing.recovery_dpi_ceiling' => ['Recovery DPI ceiling','number','160',null],
                    'processing.recovery_tile_size' => ['Recovery Deep Zoom tile size','number','384',null],
                    'processing.recovery_ocr_timeout_seconds' => ['Recovery OCR timeout / page (seconds)','number','450',null],
                ],
            ],
            'clipping' => [
                'label' => 'Clipping',
                'description' => 'Final City Clip Mode defaults: select, save, re-select, cancel and share.',
                'fields' => [
                    'clipping.enabled' => ['Clipping','select','on',['on','off']],
                    'clipping.default_visibility' => ['Default visibility','select','PRIVATE',['PRIVATE','PUBLIC']],
                    'clipping.watermark' => ['Publisher branding','fixed','Always on',null],
                    'clipping.expiry_days' => ['Clip expiry','fixed','7 days',null],
                ],
            ],
        ];
    }

    public static function group(string $key): ?array
    {
        return self::groups()[$key] ?? null;
    }

    public static function defaults(): array
    {
        $out=[];
        foreach(self::groups() as $group){
            foreach($group['fields'] as $key=>$field)$out[$key]=$field[2];
        }
        return $out;
    }

    public static function keys(): array{return array_keys(self::defaults());}
}
