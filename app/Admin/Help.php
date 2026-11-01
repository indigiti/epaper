<?php
declare(strict_types=1);

namespace Digiti\Admin;

use Digiti\Core\View;

/** Plain-language help for operators; links only to existing authenticated workflows. */
final class Help
{
    public static function index(): never
    {
        $body='<section class="help-hero"><span class="home-eyebrow">HELP & GUIDANCE</span>'
            .'<h2>You do not need to understand technical terms to manage a newspaper.</h2>'
            .'<p>Choose what you are trying to do. Each guide opens the correct screen and explains the expected result.</p></section>'
            .'<div class="help-guide-grid">'
            .self::guide('Create a new publisher','A newspaper should have its own organisation and tenant so its editions and readers stay separate.',[
                ['Open “Add publisher” and enter the newspaper name and website.','/admin/onboarding?mode=new#new-publisher'],
                ['Assign an existing team member as the publisher owner, or add a user first.','/admin/users'],
                ['Complete website verification, subscription access and the go-live checklist.','/admin/onboarding'],
            ])
            .self::guide('Wrong newspaper appears','If two hostnames show identical editions, check whether they intentionally share the same publisher tenant.',[
                ['Open the registered domains and select the wrong hostname.','/admin/onboarding#existing-domains'],
                ['Under “Change publisher tenant”, select the correct existing newspaper account.','/admin/onboarding'],
                ['Check its separate content, reverify DNS and complete go-live checks.','/admin/onboarding'],
            ])
            .self::guide('Website does not open','Domain routing, DNS and HTTPS are separate from Platform registration.',[
                ['Open the publisher’s domain tracker and read the first incomplete step.','/admin/onboarding#existing-domains'],
                ['Check Cloudways additional domains and SSL certificate coverage for that hostname.','/admin/help'],
                ['Use the DNS recheck after adding the exact CNAME or TXT record.','/admin/onboarding'],
            ])
            .self::guide('Give someone access','Users can have memberships in more than one organisation, but access must be granted intentionally.',[
                ['Create or locate the person in Team & Access.','/admin/users'],
                ['Select the correct organisation and appropriate role.','/admin/users'],
                ['Confirm the publisher dashboard only shows that organisation’s content.','/admin/onboarding'],
            ])
            .'</div>'
            .'<section class="home-section help-glossary"><div class="home-heading"><div><span class="home-eyebrow">PLAIN LANGUAGE</span><h3>What the technical terms mean</h3></div></div>'
            .'<dl class="help-terms">'
            .'<div><dt>Organisation</dt><dd>The company or publisher that owns the newspaper account.</dd></div>'
            .'<div><dt>Product tenant</dt><dd>The publisher’s separate data space. Different newspapers need separate tenants when their content must be isolated.</dd></div>'
            .'<div><dt>Domain</dt><dd>The website address, such as epaper.dcnnews.co.in.</dd></div>'
            .'<div><dt>Application</dt><dd>The shared software installation serving multiple customers.</dd></div>'
            .'<div><dt>DNS</dt><dd>Records that direct the newspaper hostname to the correct application.</dd></div>'
            .'<div><dt>SSL / HTTPS</dt><dd>The certificate that makes the publisher’s website secure in the browser.</dd></div>'
            .'<div><dt>Go-live</dt><dd>Final operational approval after the correct newspaper and access rules have been verified.</dd></div>'
            .'</dl></section>'
            .'<div class="help-note"><strong>Safety:</strong> Do not remove an existing tenant to fix a domain. '
            .'When changing an established publisher website, preserve the original content and take backups first. '
            .'The final go-live check requires manual acceptance, not just a green DNS indicator.</div>';

        View::layout('Help Centre',$body,[
            'subtitle'=>'Plain-language instructions for daily Platform tasks',
        ]);
    }

    private static function guide(string $title,string $description,array $steps): string
    {
        $html='<article class="help-guide"><h3>'.View::esc($title).'</h3><p>'.View::esc($description).'</p><ol>';
        foreach($steps as [$text,$url]) $html.='<li>'.View::esc($text).' <a href="'.View::esc($url).'">Open</a></li>';
        return $html.'</ol></article>';
    }
}
