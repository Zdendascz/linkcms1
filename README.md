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

## Q: Stavy u navigací / článků a jak fungují:
A: Zatím to tak moc není, ale je tu myšlenka: 
-   "aktivní" (active) obsah je normálně přístupný a viditelný
-   "v přípravě" (development) obsah je na webu viditelný jen pro přihlášeného uživatele s právem přístupu do administrace
-   "skrytý" (hidden) obsah není běžně viditelný, ale kdo má url, ten se tam dostane (toto zatím není)
-   "zakázaný" (suspend) obsah je k dispozci jen v administraci
-   "smazaný" (deleted) obsah vidí jen oprávnění uživatelé

## Q: jak se řeší nastavení rozměrů obrázků
A: Řeší se prostřednictvím konfigurace proměnných. Vždy jde o dvojici rozměrů s označením [nazevPromenne]_[w/h] Přičemž název 
proměnné je typicky: banner, articles, articleDetail, galleryThumbanil ... Systém sám při nahrávání obrázků na google cloud vytvoří verze

## Q: jaké jsou varianty obrázků:
A: Platí, že rozměry všech variant se udávají v konfiguraci a mohou se měnit.
    banner (1500x635) - úvodní banner nebo banner na home
    articleDetail (1300x530) - obrázek v záhlaví detailu článku
    galleryThumbnail (500x414) - náhledy galerie, menší varianta 200x200 se používá pro náhledy v administraci
    articles (1000x486) - náhled v seznamu článků
    gallery (1200x1200) - detail v galerii
