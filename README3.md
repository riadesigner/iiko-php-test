# ИЗУЧАЕМ ВНЕШНЕЕ МЕНЮ

Загруженное внешнее меню из Pizzaiolo (15-02-2026)
- [iiko external menu (pizzaiolo)](app/exports/imported_external_menu-pizzaiolo-15-02-2026.json)

## СТРУКТУРА

В ответе json от iiko во внешнем меню есть:

- productCategories (это папки из iiko office) - не используем
- customerTagGroups (пустой массив) - не используем
- comboCategories (пустой массив) - не используем
- itemCategories (__КАТЕГОРИИ__) - забираем себе
   
А также забираем вложенные объекты:   

- itemCategories->items (__ТОВАРЫ__)

Внутри товаров есть Размеры, Группы модификаторов, Модификаторы и тд.   
Из всего меню мы берем только некоторые поля. Вот они:

- itemCategories (__КАТЕГОРИИ__)
    {
    - id
    - name
    - items (__ТОВАРЫ__)
        {
            - name
            - description
            - 
        }
    - isHidden
    },
    {
        ...
    }

   

## CHEFSMENU PARSED

1-iiko: [iiko external menu just loaded](app/exports/json-iiko-external-loaded.json)   
2-chefs: [chefsmenu parsed file](app/exports/json-chefsmenu-current-parsed-after-import.json)   

1-iiko - это загруженное внешнее меню (с тестового сервера)
2-chefs - это преобразованное меню в текущий формат chefsmenu 