# linkcms1

Výhledově jeden z web builderů: VvvebJs / https://grapesjs.com/

# FAQ
## Q: jak vytvořit novou url webu
A: V rámci databáze vytvořit nový záznam, buď přes phpmyadmin, nebo přes rozhraní aplikace
doména je v pohodě, url na konci nesmí obsahovat lomítko
hnadler je funkce, která se při zavolání stránky vykoná
model je parametr pro switch s nímž má pracovat v index.php a zároveň může jít o tabulku,
která má být zpracována.

## Q: zpracování hlášek pro uživatele a statusových info
A: předávají se přes GET, jsou vyhrazeny proměnné:
status: (success,true)/(error,false)
message: ["zprava1","zprava2"]
Zobrazení se provádí voláním js funkce **updateUrlParamsAndShowAlert(elementId)**, kde elementId je typicky id
karty, v níž je obsažen formulář. 