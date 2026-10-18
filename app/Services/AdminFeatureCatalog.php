<?php
declare(strict_types=1);
namespace EpaperSaas\Services;

/** Role-filtered display catalog; authorization remains enforced on every route. */
final class AdminFeatureCatalog
{
    /** @return array<string,list<array{0:string,1:string,2:string,3:string}>> */
    public static function groups(string $role,bool $aiEnabled,bool $infra): array
    {
        $role=strtoupper($role);
        $editor=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR'],true);
        $operator=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN','EDITOR','OPERATOR'],true);
        $full=in_array($role,['OWNER','ADMIN','PUBLISHER_ADMIN'],true);
        $groups=['PUBLISHING'=>[['Overview','/admin','Your publishing desk','dashboard']]];
        if($editor)$groups['PUBLISHING'][]=['Editions','/admin/editions','Upload, reorder and publish','pages'];
        if($operator)$groups['PUBLISHING'][]=['Processing Center','/admin/processing','Render, OCR and job recovery','activity'];
        if($editor&&$aiEnabled)$groups['PUBLISHING'][]=['AI Editorial Review','/admin/ai-review','Review clipping candidates','clip'];
        if($editor)$groups['READER & DISCOVERY']=[
            ['Reader Navigation','/admin/navigation','Menus and scopes','menu'],
            ['Articles','/admin/articles','Stories and semantic publishing','pages'],
            ['Highlights','/admin/highlights','Homepage promotions','bookmark'],
            ['Topics','/admin/topics','Curated discovery','bookmark'],
            ['Entities','/admin/entities','People, places and organisations','search'],
            ['Authors','/admin/authors','Profiles and bylines','user'],
        ];
        if($full)$groups['AUDIENCE & ACCESS']=[
            ['Subscribers','/admin/subscribers','Readers and subscriptions','user'],
            ['Plans & Entitlements','/admin/plans','Subscriber capabilities','settings'],
        ];
        if($editor)$groups['PUBLICATION SETUP']=[
            ['Publications','/admin/publications','Brands and mastheads','pages'],
            ['Master Data','/admin/master-data','Cities, languages and sections','settings'],
        ];
        if($full)$groups['PUBLICATION SETUP'][]=['Publisher Settings','/admin/settings','Brand, SEO, Reader and processing','settings'];
        if($operator)$groups['HEALTH & OPERATIONS']=[
            ['Testing & QA','/admin/system/testing','Readiness and acceptance','activity'],
            ['System Health','/admin/system/health','Database, cache and services','activity'],
            ['Error Center','/admin/errors','Incident diagnostics','signal'],
            ['Performance & Cache','/admin/performance','Speed and delivery','signal'],
        ];
        if($full)$groups['HEALTH & OPERATIONS'][]=['Storage & Backups','/admin/storage','Storage diagnostics','archive'];
        if($infra&&in_array($role,['OWNER','ADMIN'],true)){
            $groups['HEALTH & OPERATIONS'][]=['Infrastructure','/admin/system/infrastructure','RabbitMQ and service configuration','settings'];
            $groups['HEALTH & OPERATIONS'][]=['Supervisor Doctor','/admin/system/infrastructure/supervisor','Worker lane diagnostics','activity'];
            $groups['HEALTH & OPERATIONS'][]=['Web Recovery','/admin/system/recovery','Inspect and repair safely','refresh'];
            $groups['HEALTH & OPERATIONS'][]=['Upgrade Center','/admin/system/upgrade','Database preflight','settings'];
        }
        if($full)$groups['GOVERNANCE']=[
            ['Roles & Access','/admin/access','Memberships and roles','user'],
            ['Audit Trail','/admin/audit','Administrative changes','clock'],
            ['Release Audit','/admin/system/release-audit','Deployment integrity','signal'],
        ];
        $groups['ACCOUNT']=[['My Account','/admin/account','Publisher profile','user']];
        return array_filter($groups,static fn(array $items):bool=>!empty($items));
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
