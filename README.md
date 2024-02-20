# linkcms1

Výhledově jeden z web builderů: VvvebJs / https://grapesjs.com/

# ToDo
-   Správa url: selecty handleru a modelu nenačtou informace aktuálně z db a neoznačí je vždy, takže 
    se občas přepíšou už vytvořené modely nebo handlery prázdnými
-   Pořád se občas při práci s kategoriemi správně neuloží údaje
-   Při práci s url mohu čísla u model_id nahradit názvy článků či kategorií
-   Plánovaní publikace obsahu
-   Řízení přesměrování
-   Správa medií

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

## Q: Stavy u navigací / článků a jak fungují:
A: Zatím to tak moc není, ale je tu myšlenka: 
-   "aktivní" (active) obsah je normálně přístupný a viditelný
-   "v přípravě" (development) obsah je na webu viditelný jen pro přihlášeného uživatele s právem přístupu do administrace
-   "skrytý" (hidden) obsah není běžně viditelný, ale kdo má url, ten se tam dostane (toto zatím není)
-   "zakázaný" (suspend) obsah je k dispozci jen v administraci
-   "smazaný" (deleted) obsah vidí jen oprávnění uživatelé