<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/**
 * Discoverable publisher features. This catalog only controls display;
 * authorization remains enforced by the application routes.
 */
final class AdminFeatureCatalog
{
    /** @return array<string,list<array{0:string,1:string,2:string,3:string}>> */
    public static function groups(string $role,bool $aiEnabled,bool $infra): array
    {
        $role=strtoupper($role);
        $editor=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true);
        $operator=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true);
        $full=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true);

        // Primary navigation is arranged around work, not internal subsystems.
        // Every former route remains discoverable; specialist tools live under ADVANCED.
        $groups=['OVERVIEW'=>[
            ['Publishing Desk','/admin','Actions, editions and publishing status','layout-dashboard'],
        ]];
        if($editor){
            $groups['PUBLISHING']=[
                ['Editions','/admin/editions','Upload, reorder, schedule and publish','newspaper'],
                ['Clip Marking','/admin/manual-clipping','Mark articles for reader-created clippings','scan-text'],
                ['Publications','/admin/publications','Brands and newspaper editions','library-big'],
            ];
            $groups['EDITORIAL']=[
                ['Articles','/admin/articles','Stories and semantic publishing','file-text'],
                ['Highlights','/admin/highlights','Homepage promotions','bookmark'],
            ];
            if($aiEnabled)$groups['EDITORIAL'][]=
                ['AI Editorial Review','/admin/ai-review','Review and correct AI candidates','bot'];
            $groups['DISCOVERY']=[
                ['Reader Navigation','/admin/navigation','Reader menus and scopes','book-open'],
                ['Topics','/admin/topics','Curated discovery','tags'],
                ['Entities','/admin/entities','People, places and organisations','compass'],
                ['Authors','/admin/authors','Profiles and bylines','user-round'],
            ];
            $groups['SETTINGS']=[
                ['Master Data','/admin/master-data','Cities, languages and sections','sliders-horizontal'],
            ];
        }
        if($full){
            $groups['AUDIENCE']=[
                ['Subscribers','/admin/subscribers','Readers and subscriptions','users'],
                ['Plans & Entitlements','/admin/plans','Subscriber capabilities','credit-card'],
            ];
            $groups['SETTINGS'][]=['Publisher Settings','/admin/settings','Brand, SEO, Reader and processing','settings'];
            $groups['SETTINGS'][]=['Background Effects','/admin/settings/effects','Visual effects and homepage animation customizer','palette'];
            $groups['SETTINGS'][]=['Roles & Access','/admin/access','Memberships and permissions','shield-check'];
        }
        if($operator){
            $groups['OPERATIONS']=[
                ['Processing Center','/admin/processing','Render, OCR and job recovery','workflow'],
                ['Testing & QA','/admin/system/testing','Readiness and acceptance','clipboard-check'],
                ['System Health','/admin/system/health','Database, cache and services','gauge'],
                ['Error Center','/admin/errors','Incident diagnostics','alert-triangle'],
            ];
        }
        if($editor)$groups['ADVANCED']=[
            ['AI Clipping','/admin/ai-clipping','Feature readiness and setup','bot'],
        ];
        if($full){
            $groups['ADVANCED'][]=['Audit Trail','/admin/audit','Administrative changes and security events','clock'];
            $groups['ADVANCED'][]=['Release Audit','/admin/system/release-audit','Deployment integrity','file-check'];
            $groups['ADVANCED'][]=['Storage & Backups','/admin/storage','Storage diagnostics','hard-drive'];
        }
        if($operator)$groups['ADVANCED'][]=['Performance & Cache','/admin/performance','Speed and delivery','gauge'];
        if($infra){
            $groups['ADVANCED'][]=['Infrastructure','/admin/system/infrastructure','RabbitMQ and service configuration','cpu'];
            $groups['ADVANCED'][]=['Supervisor Doctor','/admin/system/infrastructure/supervisor','Worker lane diagnostics','workflow'];
            $groups['ADVANCED'][]=['Web Recovery','/admin/system/recovery','Inspect and repair safely','refresh-cw'];
            $groups['ADVANCED'][]=['Upgrade Center','/admin/system/upgrade','Database preflight','settings'];
        }
        $groups['ACCOUNT']=[['My Account','/admin/account','Publisher profile','user-round']];
        // Consistent human workflow order, independent of how role permissions are assigned.
        $ordered=[];
        foreach(['OVERVIEW','PUBLISHING','EDITORIAL','DISCOVERY','AUDIENCE','SETTINGS','OPERATIONS','ADVANCED','ACCOUNT'] as $section)
            if(!empty($groups[$section]))$ordered[$section]=$groups[$section];
        return $ordered;
    }

    /** @param array<string,list<array{0:string,1:string,2:string,3:string}>> $groups
     * @return list<array{0:string,1:string,2:string,3:string,4:string}> */
    public static function flatten(array $groups): array
    {
        $all=[];
        foreach($groups as $group=>$items)foreach($items as $item)
            $all[]=[$item[0],$item[1],$item[2],$item[3],(string)$group];
        return $all;
    }
}
