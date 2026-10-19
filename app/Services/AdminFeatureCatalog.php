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

        $groups=['OVERVIEW'=>[
            ['Publishing Desk','/admin','Actions, editions and publishing status','dashboard'],
        ]];
        if($editor)$groups['PUBLISHING']=[
            ['Editions','/admin/editions','Upload, reorder, schedule and publish','pages'],
        ];
        if($editor){
            $groups['AI & EDITORIAL']=[
                ['Manual Clipping','/admin/manual-clipping','Mark newspaper articles manually','clip'],
                ['AI Clipping','/admin/ai-clipping','Feature readiness and setup','scan-text'],
            ];
            if($aiEnabled)$groups['AI & EDITORIAL'][]=['AI Editorial Review','/admin/ai-review','Review and correct clipping candidates','clip'];
            array_push($groups['AI & EDITORIAL'],
                ['Articles','/admin/articles','Stories and semantic publishing','pages'],
                ['Highlights','/admin/highlights','Homepage promotions','bookmark'],
                ['Topics','/admin/topics','Curated discovery','bookmark'],
                ['Entities','/admin/entities','People, places and organisations','search'],
                ['Authors','/admin/authors','Profiles and bylines','user']
            );
            $groups['READER & AUDIENCE']=[
                ['Reader Navigation','/admin/navigation','Reader menus and scopes','menu'],
            ];
        }
        if($full){
            $groups['READER & AUDIENCE'][]=['Subscribers','/admin/subscribers','Readers and subscriptions','user'];
            $groups['READER & AUDIENCE'][]=['Plans & Entitlements','/admin/plans','Subscriber capabilities','settings'];
        }
        if($editor){
            $groups['MANAGE']=[
                ['Publications','/admin/publications','Brands and mastheads','pages'],
                ['Master Data','/admin/master-data','Cities, languages and sections','settings'],
            ];
        }
        if($full){
            $groups['MANAGE'][]=['Publisher Settings','/admin/settings','Brand, SEO, Reader and processing','settings'];
            $groups['MANAGE'][]=['Roles & Access','/admin/access','Memberships and roles','user'];
            $groups['MANAGE'][]=['Audit Trail','/admin/audit','Administrative changes','clock'];
            $groups['MANAGE'][]=['Release Audit','/admin/system/release-audit','Deployment integrity','signal'];
        }
        if($operator){
            $groups['OPERATIONS']=[
                ['Processing Center','/admin/processing','Render, OCR and job recovery','activity'],
                ['Testing & QA','/admin/system/testing','Readiness and acceptance','activity'],
                ['System Health','/admin/system/health','Database, cache and services','signal'],
                ['Error Center','/admin/errors','Incident diagnostics','signal'],
                ['Performance & Cache','/admin/performance','Speed and delivery','signal'],
            ];
        }
        if($full)$groups['OPERATIONS'][]=['Storage & Backups','/admin/storage','Storage diagnostics','archive'];
        if($infra){
            $groups['OPERATIONS'][]=['Infrastructure','/admin/system/infrastructure','RabbitMQ and service configuration','settings'];
            $groups['OPERATIONS'][]=['Supervisor Doctor','/admin/system/infrastructure/supervisor','Worker lane diagnostics','activity'];
            $groups['OPERATIONS'][]=['Web Recovery','/admin/system/recovery','Inspect and repair safely','refresh'];
            $groups['OPERATIONS'][]=['Upgrade Center','/admin/system/upgrade','Database preflight','settings'];
        }
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
