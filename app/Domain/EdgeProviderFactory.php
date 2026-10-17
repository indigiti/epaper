<?php
namespace Digiti\Domain;
use Digiti\Core\Config;
final class EdgeProviderFactory
{
    public static function make():EdgeProviderInterface{return strtolower((string)Config::get('edge.provider','manual'))==='cloudflare'?new CloudflareEdgeProvider():new ManualEdgeProvider();}
}
