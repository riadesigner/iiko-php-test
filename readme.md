СТРУКТУРА

Iiko_nomenclature - класс для получения данных из iiko.
Iiko_nmcl_parser - класс для парсинга данных (от GPT).
Iiko_params_test - класс для получения параметров iiko. 
Iiko_nomenclature_parse - класс для изучения структуры nomenclature.
Iiko_nomenclature_parse2 - класс для изучения структуры nomenclature. 

---
/files - папка для хранения исходников
/exports - папка для экспорта json файлов
---

// json-dev-extmenu.json ( ответ от iiko - внешнее меню "Тестовое меню 2" )
// json-info-formated-full-new.json (ответ от iiko - номенклатура "pizzaiolo" )

    
-----


ВЫВОДЫ

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