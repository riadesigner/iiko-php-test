# ИЗУЧАЕМ ВНЕШНЕЕ МЕНЮ

Загруженное внешнее меню из Pizzaiolo (15-02-2026)
- [iiko external menu (pizzaiolo)](app/exports/imported_external_menu-pizzaiolo-15-02-2026.json)

## ПРЕОБРАЗОВАНИЕ СТРУКТУРЫ ИМПОРТИРОВАННОГО ИЗ IIKO ВНЕШНОГО МЕНЮ 

### 1 ЭТАП

Из всего ответа json от iiko во Внешнем меню меню    
берем только некоторые поля, вот они:

- itemCategories (__КАТЕГОРИИ__)
    {
    - id
    - name
    - items (__ТОВАРЫ__)
        {
            - itemId
            - name
            - description
            - itemSizes
                {
                    - sizeCode
                    - sizeName
                    - isDefault
                    - portionWeightGrams
                    - itemModifierGroups (__ГРУППЫ МОДИФИКАТОРОВ__)
                        {
                            - name
                            - description
                            - restrictions (__ПАРАМЕТРЫ ГРУППУ МОДИФИКАТОРОВ__)
                                {
                                    - minQuantity
                                    - maxQuantity
                                }
                            - items (__МОДИФИКАТОРЫ__)
                                {
                                    - name
                                    - portionWeightGrams
                                    - itemId
                                    - prices
                                        {
                                            - price
                                        }
                                    - restrictions
                                        {
                                            - byDefault
                                        }
                                    - position
                                    - measureUnitType
                                },
                                ...
                            - isHidden
                        },
                        ...
                    - sizeId
                    - nutritionPerHundredGrams 
                        {
                            - fats
                            - proteins
                            - carbs
                            - energy                       
                        } 
                    - measureUnitType
                    - prices 
                        { 
                            - price
                        } 
                    - isHidden
                    - buttonImageUrl                                     
                },
                ...           
            - modifiers
            - orderItemType            
        },
        ...
    - isHidden
    },
    ...
   

### 2 ЭТАП

Переименовываем некоторые поля, а так же упрощаем часть структуры.    
Получившуюся структуру будем называть __CHEFSMENU__.

При этом файл уменьшается с 7.5 мб до 1.6 мб.  

Вот преобразованное меню в формат CHEFSMENU.  
File: [chefsmenu parsed file](app/exports/2026-02-17_12-28-29_5e0de008-chefs.json)   

Окончательная струтура:

{
    - id (EXTERNAL ID MENU)
    - name 
    - categories (__КАТЕГОРИИ__) 
        {
            - id
            - name
            - items (__ТОВАРЫ__)
                {
                    - id
                    - name
                    - description
                    - sizes 
                        {
                            - sizeCode
                            - sizeId
                            - sizeName
                            - isDefault
                            - measureUnitType
                            - portionWeightGrams
                            - price
                        },                        
                        ...
                    - imageUrl
                    - modifiers (__ГРУППЫ МОДИФИКАТОРОВ__)
                        {
                            - modifierGroupId
                            - name
                            - restrictions (__ПАРАМЕТРЫ ГРУППЫ__)
                                {
                                    - minQuantity
                                    - maxQuantity
                                }
                            - items (__МОДИФИКАТОРЫ__)
                                {
                                    - modifierId
                                    - name
                                    - price
                                    - position                                    
                                    - portionWeightGrams
                                    - measureUnitType
                                    - byDefault                                    
                                }
                        }
                    - orderItemType
                    - nutritionPerHundredGrams
                        {
                            - fats
                            - proteins
                            - carbs
                            - energy                                    
                        }                    
                },
                ...
        },
        ...
        
}

Особенность формата chefsmenu в том (в том числе), что CATEGORIES и ITEMS (ТОВАРЫ) - хранятся как ассоциативный массив (с id ключами ), а MODIFIERS и ITEMS (ГРУППЫ МОДИФИКАТОРОВ и МОДИФИКАТОРЫ) - как обычные массивы (с индексами 0, 1, 2, ...);   

ИССЛЕДОВАТЬ

На каком-то этапе было решено использовать для КАТЕГОРИЙ и ТОВАРОВ способ хранения в ассоциативном массиве, а не просто в массиве. Возможно это было не лучшее решение.  
Возможно позже можно попробовать уйти от этого решения. 
