<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

use EpaperSaas\Core\{Auth,RequestContext,View};

/**
 * Additional direct-link authorization for publisher administration.
 *
 * The sidebar catalog is presentation only. This policy is enforced for every
 * /admin request after Auth::requireAdmin() and does not replace the existing
 * stronger canonical-host infrastructure controls in individual handlers.
 */
final class AdminRoutePolicy
{
    private const EDITORS=['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'];
    private const MANAGERS=['OWNER','ADMIN','PUBLISHER_ADMIN'];

    public static function requiredCapability(string $path): string
    {
        if (self::matches($path,[
            '/admin/subscribers','/admin/plans','/admin/settings',
            '/admin/access','/admin/audit','/admin/storage',
            '/admin/system/release-audit',
        ]))return 'MANAGER';

        if (self::matches($path,[
            '/admin/editions','/admin/navigation','/admin/publications',
            '/admin/highlights','/admin/articles','/admin/authors',
            '/admin/topics','/admin/entities','/admin/master-data',
            '/admin/manual-clipping','/admin/ai-clipping','/admin/ai-review',
        ]))return 'EDITOR';

        // Infrastructure routes continue to enforce their separate configurable
        // role list AND canonical host constraint in the existing handlers.
        return 'ADMIN';
    }

    /** Explicit allowlist for the narrow WebDesk publishing workflow. */
    public static function webDeskAllows(string $path,string $method='GET'): bool
    {
        $method=strtoupper(trim($method));
        if($path==='/admin'||$path==='/admin/editions'||$path==='/admin/manual-clipping')
            return $method==='GET'||($path==='/admin/editions'&&$method==='POST');
        if($path==='/admin/account')return $method==='GET';
        if(preg_match('#^/admin/editions/[0-9a-f-]{36}/publish$#i',$path))
            return $method==='POST';
        if(preg_match('#^/admin/manual-clipping/[0-9a-f-]{36}$#i',$path))
            return in_array($method,['GET','POST'],true);
        if(preg_match('#^/admin/manual-clipping/[0-9a-f-]{36}/preview$#i',$path))
            return $method==='GET';
        if(preg_match('#^/admin/manual-clipping/[0-9a-f-]{36}/marks/[0-9a-f-]{36}/update$#i',$path))
            return $method==='POST';
        return false;
    }

    public static function allows(string $path,string $role,string $method='GET'): bool
    {
        $role=strtoupper(trim($role));
        if($role==='WEBDESK')return self::webDeskAllows($path,$method);
        return match(self::requiredCapability($path)){
            'MANAGER'=>in_array($role,self::MANAGERS,true),
            'EDITOR'=>in_array($role,self::EDITORS,true),
            default=>in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true),
        };
    }

    public static function enforce(string $path,string $method='GET'): void
    {
        $role=(string)(Auth::currentMembership()['role_key']??'');
        if(self::allows($path,$role,$method))return;
        http_response_code(403);
        View::layout('Access denied',
            '<section class="empty-state"><h1>Access denied</h1>'
           .'<p>Your publisher role cannot access this administration module.</p>'
           .'<a class="btn" href="/admin">Return to Publishing Desk</a></section>',
            ['brand'=>(string)RequestContext::get('tenant_name','ePaper SaaS'),'admin'=>true]);
    }

    private static function matches(string $path,array $prefixes): bool
    {
        foreach($prefixes as $prefix){
            if($path===$prefix||str_starts_with($path,$prefix.'/'))return true;
        }
        return false;
    }
}
