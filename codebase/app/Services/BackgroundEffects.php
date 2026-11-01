<?php
declare(strict_types=1);

namespace EpaperSaas\Services;

use InvalidArgumentException;

/**
 * Single source of truth for multi-tenant Vanta options, server validation,
 * allowed effect assets, and safe browser configuration.
 */
final class BackgroundEffects
{
    public const EFFECTS = ['Off','Halo','Waves','Net','Dots','Rings','Clouds','Cells','Fog'];

    /** Field metadata: label, control, default, min, max, step, applicable effects. */
    public const FIELDS = [
        'home_effect.background_color'=>['Background color','color','#F4F8FF',null,null,null,['Halo','Net','Dots','Rings','Clouds','Cells']],
        'home_effect.base_color'=>['Primary effect color','color','#3877BD',null,null,null,['Halo','Waves','Net','Dots','Fog','Cells']],
        'home_effect.secondary_color'=>['Secondary color','color','#67C9C3',null,null,null,['Halo','Dots','Cells']],
        'home_effect.opacity'=>['Transparency','range','0.28',0.08,0.45,0.01,['Halo','Waves','Net','Dots','Rings','Clouds','Cells','Fog']],
        'home_effect.size'=>['Size','range','1.15',0.5,2.5,0.05,['Halo','Dots','Cells']],
        'home_effect.speed'=>['Animation speed','range','0.30',0.1,1.5,0.05,['Halo','Clouds','Cells','Fog']],
        'home_effect.amplitude_factor'=>['Amplitude','range','0.75',0.2,2.0,0.05,['Halo','Cells']],
        'home_effect.x_offset'=>['Horizontal offset','range','0.12',-1,1,0.05,['Halo']],
        'home_effect.y_offset'=>['Vertical offset','range','0',-1,1,0.05,['Halo']],
        'home_effect.halo_ring'=>['Ring intensity','range','1.15',0.3,2.0,0.05,['Halo','Cells']],
        'home_effect.halo_rotation'=>['Rotation','range','0.18',0,2.0,0.05,['Halo','Cells']],
        'home_effect.wave_height'=>['Wave height','range','12',2,30,1,['Waves']],
        'home_effect.wave_speed'=>['Wave speed','range','0.5',0.1,1.5,0.05,['Waves']],
        'home_effect.wave_shininess'=>['Shininess','range','30',0,100,1,['Waves']],
        'home_effect.wave_zoom'=>['Wave zoom','range','1',0.55,1.6,0.05,['Waves']],
        'home_effect.net_points'=>['Network points','range','8',4,16,1,['Net']],
        'home_effect.net_distance'=>['Connection distance','range','18',8,35,1,['Net']],
        'home_effect.net_spacing'=>['Network spacing','range','18',12,30,1,['Net']],
        'home_effect.net_dots'=>['Show nodes','select','On',null,null,null,['Net']],
        'home_effect.dots_spacing'=>['Dot spacing','range','35',18,60,1,['Dots']],
        'home_effect.dots_lines'=>['Connect dots','select','On',null,null,null,['Dots']],
        'home_effect.cloud_sky'=>['Sky color','color','#9DCCE0',null,null,null,['Clouds']],
        'home_effect.cloud_color'=>['Cloud color','color','#DCE8F5',null,null,null,['Clouds']],
        'home_effect.cloud_shadow'=>['Cloud shadow','color','#94B6CD',null,null,null,['Clouds']],
        'home_effect.cloud_sun'=>['Sunlight color','color','#FFE4A7',null,null,null,['Clouds']],
        'home_effect.fog_highlight'=>['Highlight color','color','#B3E9E3',null,null,null,['Fog']],
        'home_effect.fog_midtone'=>['Midtone color','color','#80B7DC',null,null,null,['Fog']],
        'home_effect.fog_lowlight'=>['Lowlight color','color','#B8D0EF',null,null,null,['Fog']],
        'home_effect.fog_blur'=>['Fog softness','range','0.6',0.2,1.5,0.05,['Fog']],
    ];

    public static function asset(string $effect): ?string
    {
        return match($effect) {
            'Halo'=>'/assets/vendor/vanta.halo.0.5.24.min.js',
            'Waves'=>'/assets/vendor/vanta.waves.0.5.24.min.js',
            'Net'=>'/assets/vendor/vanta.net.0.5.24.min.js',
            'Dots'=>'/assets/vendor/vanta.dots.0.5.24.min.js',
            'Rings'=>'/assets/vendor/vanta.rings.0.5.24.min.js',
            'Clouds'=>'/assets/vendor/vanta.clouds.0.5.24.min.js',
            'Cells'=>'/assets/vendor/vanta.cells.0.5.24.min.js',
            'Fog'=>'/assets/vendor/vanta.fog.0.5.24.min.js',
            default=>null
        };
    }

    public static function defaults(): array
    {
        $values=['appearance.home_background_effect'=>'Off'];
        foreach(self::FIELDS as $key=>$field)$values[$key]=(string)$field[2];
        return $values;
    }

    public static function saved(): array
    {
        $values=[];
        foreach(self::defaults() as $key=>$default) {
            $values[$key]=TenantData::setting($key,$default);
        }
        return self::normalize($values);
    }

    /**
     * Validate all user-controlled settings before the first DB write.
     * No arbitrary CSS, JS, asset paths or unbounded shader parameters.
     */
    public static function normalize(array $input): array
    {
        $defaults=self::defaults();
        $effect=(string)($input['appearance.home_background_effect']??'Off');
        if(!in_array($effect,self::EFFECTS,true)){
            throw new InvalidArgumentException('Select a supported background effect.');
        }
        $output=['appearance.home_background_effect'=>$effect];
        foreach(self::FIELDS as $key=>$field){
            [$label,$type,$default,$min,$max]=$field;
            $raw=trim((string)($input[$key]??$default));
            if($type==='color'){
                if(!preg_match('/^#[0-9a-fA-F]{6}$/D',$raw)){
                    throw new InvalidArgumentException($label.' requires a six-digit hex color.');
                }
                $output[$key]=strtoupper($raw);
            }elseif($type==='select'){
                if(!in_array($raw,['On','Off'],true)){
                    throw new InvalidArgumentException($label.' must be On or Off.');
                }
                $output[$key]=$raw;
            }else{
                if($raw===''||!is_numeric($raw)){
                    throw new InvalidArgumentException($label.' must be numeric.');
                }
                $number=(float)$raw;
                if(!is_finite($number)||$number<(float)$min||$number>(float)$max){
                    throw new InvalidArgumentException($label.' must be between '.$min.' and '.$max.'.');
                }
                $output[$key]=(string)round($number,3);
            }
        }
        return $output;
    }

    public static function fromPost(array $posted): array
    {
        $values=self::defaults();
        foreach($values as $key=>$default){
            // Exact names are selected, never iterated over arbitrary input keys.
            $values[$key]=array_key_exists($key,$posted)?$posted[$key]:$default;
        }
        return self::normalize($values);
    }

    public static function browserOptions(array $values): array
    {
        $v=self::normalize($values);
        $get=static fn(string $key):float=>(float)$v['home_effect.'.$key];
        return [
            'backgroundColor'=>$v['home_effect.background_color'],
            'baseColor'=>$v['home_effect.base_color'],
            'secondaryColor'=>$v['home_effect.secondary_color'],
            'opacity'=>$get('opacity'),
            'size'=>$get('size'),
            'speed'=>$get('speed'),
            'amplitudeFactor'=>$get('amplitude_factor'),
            'xOffset'=>$get('x_offset'),
            'yOffset'=>$get('y_offset'),
            'ringFactor'=>$get('halo_ring'),
            'rotationFactor'=>$get('halo_rotation'),
            'waveHeight'=>$get('wave_height'),
            'waveSpeed'=>$get('wave_speed'),
            'shininess'=>$get('wave_shininess'),
            'zoom'=>$get('wave_zoom'),
            'points'=>$get('net_points'),
            'maxDistance'=>$get('net_distance'),
            'spacing'=>$get('net_spacing'),
            'showDots'=>$v['home_effect.net_dots']==='On',
            'dotSpacing'=>$get('dots_spacing'),
            'showLines'=>$v['home_effect.dots_lines']==='On',
            'skyColor'=>$v['home_effect.cloud_sky'],
            'cloudColor'=>$v['home_effect.cloud_color'],
            'cloudShadowColor'=>$v['home_effect.cloud_shadow'],
            'sunColor'=>$v['home_effect.cloud_sun'],
            'fogHighlight'=>$v['home_effect.fog_highlight'],
            'fogMidtone'=>$v['home_effect.fog_midtone'],
            'fogLowlight'=>$v['home_effect.fog_lowlight'],
            'blurFactor'=>$get('fog_blur'),
        ];
    }
}
