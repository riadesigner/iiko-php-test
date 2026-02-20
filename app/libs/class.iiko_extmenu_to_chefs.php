<?php
/**
 * ПРЕОБРАЗУЕМ ВНЕШНЕЕ МЕНЮ В CHEFSMENU (v-1.2.0)
 *  
 * updated: 17-02-2026 
 * 
 * РЕАЛИЗАЦИЯ
 * особенность формата chefsmenu в том (в том числе), что 
 * CATEGORIES и ITEMS (ТОВАРЫ) - хранятся как ассоциативный массив (с id ключами ),
 * а MODIFIERS и ITEMS (ГРУППЫ МОДИФИКАТОРОВ и МОДИФИКАТОРЫ) - как обычные массивы (с индексами 0, 1, 2, ...);
 * 
 * */


class Iiko_extmenu_to_chefs {

    public static function parse($extMenu) {

        $MENU = [
            'id' => $extMenu['id'] ?? null,
            'name' => $extMenu['name'] ?? null,
            'categories' => [],
            'description' => $extMenu['description'] ?? null
        ];

        $cats = $extMenu['itemCategories'] ?? [];

        foreach ($cats as $catData) {

            $cat = [
                'id' => $catData['id'] ?? null,
                'name' => $catData['name'] ?? null,
                'items' => []
            ];

            $MENU['categories'][$cat['id']] = $cat;

            $items = $catData['items'] ?? [];

            foreach ($items as $itemData) {

                $item = [
                    'id' => $itemData['itemId'] ?? null,
                    'name' => $itemData['name'] ?? null,
                    'description' => $itemData['description'] ?? null,
                    'sizes' => [],
                    'imageUrl' => "",
                    'modifiers' => [],
                    'orderItemType' => $itemData['orderItemType'] ?? null,
                    'nutritionPerHundredGrams'=>"",
                ];

                $sizes = [];
                $itemSizes = $itemData['itemSizes'] ?? [];                

                // собираем размеры, цены и вес
                foreach ($itemSizes as $size) {
                    $sizes[] = [
                        'sizeCode' => $size['sizeCode'] ?? null,
                        'sizeId' => $size['sizeId'] ?? null,
                        'sizeName' => $size['sizeName'] ?? null,
                        'isDefault' => $size['isDefault'] ?? null,
                        'measureUnitType' => $size['measureUnitType'] ?? null,
                        'portionWeightGrams' => $size['portionWeightGrams'] ?? null,                        
                        'price' => $size['prices'][0]['price'] ?? null
                    ];

                    // берем только первое описание калорий
                    if(empty($item['nutritionPerHundredGrams'])){
                        $item['nutritionPerHundredGrams'] = $size['nutritionPerHundredGrams'];
                    }

                    // берем только первую картинку
                    if (!empty($size['buttonImageUrl']) && empty($item['imageUrl'])) {
                        $item['imageUrl'] = $size['buttonImageUrl'];
                    }

                    // собираем модификаторы только один раз
                    if (empty($item['modifiers']) && !empty($size['itemModifierGroups'])) {
                        $arr_m = [];
                        foreach ($size['itemModifierGroups'] as $modGroup) {
                            $m = [
                                'modifierGroupId' => $modGroup['itemGroupId'] ?? '',
                                'name' => $modGroup['name'] ?? '',
                                'restrictions' => [
                                    'max' => $modGroup['restrictions']['max'] ?? 0,
                                    'min' => $modGroup['restrictions']['min'] ?? 0,
                                ],
                                'items' => []
                            ];

                            foreach ($modGroup['items'] as $modifier) {
                                $m['items'][] = [
                                    'modifierId' => $modifier['itemId'] ?? '',
                                    'name' => $modifier['name'] ?? '',
                                    'price' => $modifier['prices'][0]['price'] ?? 0,
                                    'position' => $modifier['position'] ?? 0,
                                    'portionWeightGrams' => $modifier['portionWeightGrams'] ?? 0,
                                    'measureUnitType' => $modifier['measureUnitType'] ?? 'GRAM',
                                    'byDefault' => $modifier['restrictions']['byDefault'] ?? 0,
                                ];                                
                            }

                            $arr_m[] = $m;
                        }
                        $item['modifiers'] = $arr_m;
                    }
                }

                $item['sizes'] = $sizes;
                $MENU['categories'][$cat['id']]['items'][$item['id']] = $item;
            }
        }

        return $MENU;
    }
}




?>