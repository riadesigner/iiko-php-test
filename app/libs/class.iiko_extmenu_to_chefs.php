<?php
/**
 * ПРЕОБРАЗУЕМ ВНЕШНЕЕ МЕНЮ В CHEFSMENU (v-1.0.0)
 *  
 * до этого делали это в js
 * принято решение передать эту функцию в php, 
 * т.к. изменился подход в передаче данных,
 * теперь при загрузке внешнего меню, преобразуется и сохраняется в базе данных
 * а на фронт передается только id загруженного меню.
 *  
 * далее фронт дает команду обновить (апгрейдить) меню в соответствии с новыми данными
 * и также передает на бэк только id загруженного меню. 
 * 
 * ОБЪЯСНЕНИЕ
 * т.к. загрузка внешнего меню происходит на стороне сервера и обновление (апгрейд) также на стороне, 
 * то гораздо эффективнее оставаться в нем (в php), а не проеобразовывать меню в js только для того, 
 * чтобы перестроить структуру и снова отдать на сервер, преобразуя в php.
 * 
 * РЕАДИЗАЦИЯ
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
                    'sku' => $itemData['sku'] ?? null
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
                        'nutritionPerHundredGrams' => $size['nutritionPerHundredGrams'] ?? null,
                        'price' => $size['prices'][0]['price'] ?? null
                    ];

                    // берем только первую картинку
                    if (!empty($size['buttonImageUrl']) && empty($item['imageUrl'])) {
                        $item['imageUrl'] = $size['buttonImageUrl'];
                    }

                    // собираем модификаторы только один раз
                    if (empty($item['modifiers']) && !empty($size['itemModifierGroups'])) {
                        $arr_m = [];
                        foreach ($size['itemModifierGroups'] as $modGroup) {
                            $m = [
                                'modifierGroupId' => $modGroup['itemGroupId'] ?? null,
                                'name' => $modGroup['name'] ?? null,
                                'restrictions' => $modGroup['restrictions'] ?? null,
                                'items' => []
                            ];

                            foreach ($modGroup['items'] as $modifier) {
                                $m['items'][] = [
                                    'modifierId' => $modifier['itemId'] ?? null,
                                    'name' => $modifier['name'] ?? null,
                                    'description' => $modifier['description'] ?? null,
                                    'portionWeightGrams' => $modifier['portionWeightGrams'] ?? null,
                                    'restrictions' => $modifier['restrictions'] ?? null,
                                    'nutritionPerHundredGrams' => $modifier['nutritionPerHundredGrams'] ?? null,
                                    'price' => $modifier['prices'][0]['price'] ?? null
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