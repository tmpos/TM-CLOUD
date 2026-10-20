<?php
declare(strict_types=1);
namespace App\Services;

final class StorefrontDesignService
{
    public static function defaults(): array
    {
        return ['preset'=>'classic','name'=>'Diseño actual','width'=>1240,'columns'=>4,'spacing'=>32,'hero'=>'split','header'=>'standard','image_ratio'=>'square','blocks'=>[]];
    }
    public static function normalize(mixed $input): array
    {
        if (!is_array($input)) throw new \InvalidArgumentException('Diseño inválido.');
        $d = array_replace(self::defaults(), $input);
        foreach (['preset'=>['classic','modern','boutique','market','gourmet','minimal','landing','custom','executive','atelier','wellness','urban','panorama','restaurant','gym','spa','events'],'hero'=>['split','center','compact'],'header'=>['standard','center'],'image_ratio'=>['square','portrait','wide']] as $key=>$allowed) {
            if (!in_array($d[$key],$allowed,true)) throw new \InvalidArgumentException('Distribución de diseño inválida.');
        }
        foreach (['width'=>[960,1600],'columns'=>[2,5],'spacing'=>[0,96]] as $key=>[$min,$max]) {
            if (!is_int($d[$key]) || $d[$key]<$min || $d[$key]>$max) throw new \InvalidArgumentException('Dimensiones de diseño inválidas.');
        }
        if (!is_string($d['name']) || trim($d['name'])==='' || mb_strlen($d['name'])>80) throw new \InvalidArgumentException('Nombre de diseño inválido.');
        if (!is_array($d['blocks']) || !array_is_list($d['blocks']) || count($d['blocks'])>30) throw new \InvalidArgumentException('El diseño admite hasta 30 bloques.');
        $blocks=[]; $seen=[]; $sections=[];
        foreach ($d['blocks'] as $b) {
            if (!is_array($b) || !is_string($b['id']??null) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$b['id']) || isset($seen[$b['id']])) throw new \InvalidArgumentException('Bloque inválido o repetido.');
            $seen[$b['id']]=true;
            if (!in_array($b['type']??'', ['section','text','image','button','spacer'],true) || !in_array($b['align']??'', ['left','center'],true) || !is_string($b['background']??null) || !preg_match('/^#[0-9a-fA-F]{6}$/D',$b['background'])) throw new \InvalidArgumentException('Opciones del bloque inválidas.');
            if (!in_array($b['section']??'', StorefrontEditorService::SECTIONS,true)) throw new \InvalidArgumentException('Sección de bloque inválida.');
            if ($b['type']==='section') {
                if (isset($sections[$b['section']])) throw new \InvalidArgumentException('Cada sección de la tienda puede añadirse una vez.');
                $sections[$b['section']]=true;
            }
            foreach (['title'=>200,'text'=>8000] as $key=>$max) if (!is_string($b[$key]??null) || mb_strlen($b[$key])>$max) throw new \InvalidArgumentException('Texto de bloque inválido.');
            $blocks[]=['id'=>$b['id'],'type'=>$b['type'],'section'=>$b['section'],'title'=>trim($b['title']),'text'=>trim($b['text']),'image'=>StorefrontEditorService::image($b['image']??''),'link'=>StorefrontEditorService::image($b['link']??'',false),'align'=>$b['align'],'background'=>$b['background']];
        }
        return array_intersect_key(array_replace($d,['blocks'=>$blocks]),self::defaults());
    }
}
