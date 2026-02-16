# ИЗУЧАЕМ IIKO ФОРМАТ МЕНЮ, ВЫГРУЖЕННОГО ИЗ НОМЕНКЛАТУРЫ  

## СТРУКТУРА ПРОЕКТА

- /exports - папка для экспорта json файлов
- /libs - папка для хранения классов и функций

## ФАЙЛЫ ДЛЯ ПАРСИНГА JSON

- [json-info-formated-full-new.json](app/exports/json-info-formated-full-new.json) / ответ от iiko - номенклатура "pizzaiolo"


## КЛАССЫ / ФУНКЦИИ

Iiko_nomenclature - класс для получения данных из iiko.
Iiko_params_test - класс для получения параметров iiko. 
Iiko_nomenclature_parse - класс для изучения структуры nomenclature.
Iiko_nomenclature_parse2 - класс для изучения структуры nomenclature. 

- [Iiko_parser_to_unimenu](app/libs/class.iiko_parser_to_unimenu.php) / класс для парсинга json от iiko в формат UNIMENU.
- [Conv_unimenu_to_chefs](app/libs/class.conv_unimenu_to_chefs.php) / класс для конвертации json UNIMENU в текущий формат CHEFSMENU.

## UMENU

UMENU - это промежуточный облегченный формат номенклатуры


## ВЫВОДЫ О СТРУКТУРЕ ВЫГРУЖЕННОЙ НОМЕНКЛАТУРЫ

В ответе json от iiko в номенклатуре есть:

- Groups
- ProductCategories
- Products

------ 

Groups это: 
    
    - ПАПКИ (в т.ч. вложенные) С ТОВАРАМИ и параметром {"isGroupModifier": false}
    – ГРУППЫ МОДИФИКАТОРОВ {"isGroupModifier": true}

ProductCategories это:
    
    - КАТЕГОРИИ ТОВАРОВ

    Нужно посмотреть как их создавать в iiko. 
    Меню можно построить двумя способами 
    - через категории 
    - через обычные папки

Products это:

    – ТОВАРЫ {type: Dish}
    – СЕРВИС {type: Service}
    – МОДИФИКАТОРЫ {type: Modifier}

    При этом:

    Модификаторы – это обычные товары
    в товаре Модификаторы могут быть указаны
    
    – просто списком "modifiers": []
    – или в группах модификаторов "groupModifiers": []

    Сервис - это товар-услуга 

    - например Доставка