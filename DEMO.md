# DEMO.md — Jak tuto aplikaci předvést někomu, kdo nezná AI, MCP ani Claude

Tenhle návod je **scénář živé prezentace**. Je napsaný pro situaci, kdy máš před sebou
člověka (kolegu, kamaráda, netechnického šéfa), který **nikdy neslyšel** o AI asistentech,
o „MCP" ani o nástroji Claude Code — a ty mu za ~15 minut chceš ukázat, proč je to zajímavé.

Cílem **není** naučit ho pojmy. Cílem je, aby si odnesl jeden jasný dojem:

> „Ten AI nástroj si sám otevřel prohlížeč, proklikal moji aplikaci jako živý tester
> a řekl mi, jestli funguje — a přitom jsem nenapsal ani řádek testu."

---

## 0. Co divákovi vlastně ukazujeme (v jedné větě)

Malou webovou aplikaci — **seznam úkolů** (to-do list) — a k ní **AI asistenta, který
umí tu aplikaci sám ovládat v prohlížeči** a ověřit, že funguje. To „umí ovládat prohlížeč"
je ta věc, která lidi překvapí.

Nepředvádíme kód. Předvádíme **schopnost**.

---

## 1. Slovníček bez žargonu (pro tebe, ať to umíš vysvětlit jednou větou)

Divák tyhle pojmy znát nemusí, ale ty ano — a musíš je umět říct lidsky. Použij analogie:

| Pojem | Řekni to takhle (analogie) |
|---|---|
| **AI asistent / Claude** | „Chytrý pomocník, kterému píšu úkoly normální řečí, a on je udělá." |
| **Claude Code** | „Ten pomocník, ale usazený přímo v počítači programátora — vidí soubory a umí spouštět programy." |
| **MCP** | „Zásuvka. Jako USB-C. Umožňuje tomu pomocníkovi připojit další nástroje — třeba prohlížeč." |
| **Playwright** | „Dálkové ovládání prohlížeče. Pomocník přes něj kliká a píše jako člověk." |
| **Context7** | „Přípojka do vždy aktuální dokumentace — aby pomocník nedával zastaralé rady." |
| **Docker** | „Krabička, ve které aplikace běží, ať mám na počítači cokoli. Nemusím nic instalovat." |

> ⚠️ **Nezahlcuj.** Divákovi stačí **AI pomocník** + **umí ovládat prohlížeč**. Zbytek
> vysvětluj, jen když se zeptá. Slovo „MCP" klidně ani nevyslovuj — je to interní název té „zásuvky".

---

## 2. Příprava (5 minut o samotě, PŘED divákem)

Tohle si odbav dřív, než někoho posadíš vedle sebe. Živé „ono to nejede" zabíjí dojem.

```bash
# 1) Rozběhni aplikaci (běží v Dockeru, port 8080)
cd task-library
docker compose up -d

# 2) Ověř, že odpovídá
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080   # čekáš: HTTP 200
```

Pak:
- Otevři si v prohlížeči **http://localhost:8080** a nech tam prázdnou záložku připravenou.
- Otevři si druhé okno / terminál s **Claude Code** spuštěným v tomto projektu.
- V Claude Code napiš `/mcp` a ověř, že vidíš **playwright** a **context7** jako `✓ Connected`.
  (Kdyby ne — to je přesně ta situace, kterou řeší návod v `README.md`, sekce 3.)
- Zvětši písmo v terminálu i prohlížeči (Ctrl + +), ať divák vidí.

**Kontrolní seznam před startem:**
- [ ] `http://localhost:8080` ukazuje „Seznam úkolů" se seznamem a zeleným progress barem
- [ ] `/mcp` hlásí oba servery připojené
- [ ] Terminál i prohlížeč mají velké písmo
- [ ] Víš, kterou aplikaci ukazuješ (viz sekce 3), a umíš to říct jednou větou

---

## 3. Sama aplikace — co to je (řekni divákovi na úvod, ~1 min)

Ukaž **http://localhost:8080** a popiš prostě, co vidí:

- Je to **seznam úkolů**. Nahoře **kolik procent je hotovo** (zelený proužek).
- Do políčka napíšu úkol, dám **Přidat** → objeví se v seznamu.
- **Kliknutím na úkol** ho odškrtnu jako hotový (✅) — a proužek nahoře povyroste.
- **Smazat** úkol odebere.
- Nahoře je odkaz **Audit log** — „černá skříňka": zapisuje se do něj **každá** změna
  (vytvoření, dokončení, smazání) a **nikdy se z něj nic nemaže**. Jako lodní deník.

Jednou větou pro diváka:
> „Úplně obyčejná appka na úkoly. Za chvíli ji nebudu proklikávat já — nechám to udělat AI."

> 💡 Nemluv o kódu, PHP, vrstvách ani databázi. Pro tenhle demo je aplikace **záminka**,
> na které se ukáže ten pomocník. Technické detaily jsou v `ARCHITECTURE.md`, kdyby padl dotaz.

---

## 4. Hlavní scénář prezentace (časový rozpis ~12 min)

Rozděl to na tři krátké „akty". Po každém udělej pauzu a nech divákovi prostor.

### 🎬 Akt 1 — „Nechám AI proklikat aplikaci místo sebe" (~5 min) — TADY JE POINTA

Tohle je moment, kvůli kterému celý demo děláš. Řekni nahlas, **co se chystáš udělat**,
ať divák chápe váhu toho, co uvidí:

> „Teď tomu pomocníkovi normální větou zadám, ať otevře moji aplikaci v prohlížeči,
> přidá úkol, odškrtne ho a ověří, že se všechno správně spočítalo. Sám. Dívej se na prohlížeč."

Do Claude Code vlož **přesně tenhle prompt** (klidně si ho předem zkopíruj):

```
Otevři http://localhost:8080, přidej úkol "Zavolat zákazníkovi",
ověř, že se objevil v seznamu, pak ho odškrtni jako hotový a zkontroluj,
že se procento nahoře správně přepočítalo. Nakonec ověř, že se obě akce
(vytvoření i dokončení) zapsaly do audit logu na /audit. Udělej screenshot.
```

**Co komentuj divákovi, zatímco to běží** (mluv u toho, ať to není ticho):
1. „Vidíš? Sám si otevřel prohlížeč."
2. „Teď našel políčko a **píše** do něj — jako člověk, ne přes žádný trik s pixely,
   ale tak, jak stránku 'čte' i odečítač pro nevidomé."
3. „Kliknul na úkol, odškrtl ho — a **sám si zkontroloval**, že proužek nahoře vzrostl."
4. „A teď šel do audit logu ověřit, že se změny zapsaly."
5. „Na konec si udělal fotku obrazovky jako důkaz."

**Pointa, kterou musíš vyslovit nahlas:**
> „Právě otestoval moji aplikaci od začátku do konce — a já nenapsal jediný řádek testu.
> Po každé změně můžu nechat udělat tuhle kontrolu znovu, za pár vteřin."

> 🧩 **Proč to funguje spolehlivě** (řekni, jen když se ptají): pomocník stránku nečte
> podle obrázku, ale podle její **struktury** (nadpisy, tlačítka, políčka) — stejně jako
> asistivní technologie pro nevidomé. Proto se neztratí, i když se změní barvy nebo rozložení.

### 🎬 Akt 2 — „Audit log jako důkaz" (~2 min)

Přepni do prohlížeče na **http://localhost:8080/audit** a ukaž tabulku:

- „Každá akce má svůj řádek: **kdy**, **co** (➕ vytvořeno / ✅ hotovo / 🗑️ smazáno) a **detail**."
- Klíčová věta: „A tenhle zápis **nejde přepsat ani smazat**. I když úkol smažu, jeho historie
  v logu zůstane. To je přesně to, co chceš, když potřebuješ dohledat, kdo co kdy udělal."

Tím ukážeš, že to není jen „hračka" — appka má vlastnost (nezměnitelný audit), která dává smysl i v reálu.

### 🎬 Akt 3 — „AI, které nedává zastaralé rady" (~3 min) — volitelné, pro techničtější publikum

Tenhle akt vynech, pokud je divák úplný laik — je abstraktnější. Pro programátora je ale silný.

Řekni:
> „Běžný problém AI: poradí ti postup, který byl aktuální před dvěma lety. Tohle to řeší —
> umí si stáhnout **současnou** dokumentaci té konkrétní knihovny, než něco navrhne."

Do Claude Code vlož:

```
use context7: ukaž mi aktuální Bootstrap 5.3 markup pro "toast" oznámení,
které by se v naší aplikaci hodilo zobrazit po přidání úkolu.
```

Komentář: „Slovíčko `use context7` je jako říct 'napřed si nastuduj aktuální manuál, pak odpovídej'.
Nevymýšlí to z hlavy — vychází z živé dokumentace."

### 🎬 Závěr (~1 min)

Shrň to jednou myšlenkou, ne výčtem:
> „Neukazoval jsem ti chytré řeči chatbota. Ukazoval jsem ti asistenta, který **udělá práci** —
> otevře aplikaci, otestuje ji, ověří výsledek a doloží ho. To je ten rozdíl."

---

## 5. Přesné prompty k dispozici (zkopíruj a měj po ruce)

**Hlavní (Akt 1):**
```
Otevři http://localhost:8080, přidej úkol "Zavolat zákazníkovi",
ověř, že se objevil v seznamu, pak ho odškrtni jako hotový a zkontroluj,
že se procento nahoře správně přepočítalo. Nakonec ověř, že se obě akce
zapsaly do audit logu na /audit. Udělej screenshot.
```

**Kratší varianta (když máš málo času):**
```
Otevři http://localhost:8080, přidej úkol "Test", odškrtni ho jako hotový
a udělej screenshot výsledku.
```

**Bonus — smazání (ukáže zápis 🗑️ do auditu):**
```
Na http://localhost:8080 smaž úkol "Zavolat zákazníkovi" a ukaž mi,
že se do audit logu přidal záznam o smazání, ale starší záznamy zůstaly.
```

**Context7 (Akt 3):**
```
use context7: ukaž mi aktuální Bootstrap 5.3 markup pro "toast" oznámení.
```

---

## 6. Časté otázky diváka — a jak odpovědět

| Otázka | Odpověď jednou větou |
|---|---|
| „To si to celé vymýšlí?" | „Ne — reálně ovládá skutečný prohlížeč a čte, co je na stránce. Ten screenshot je opravdový." |
| „Nahradí to programátory?" | „Spíš jim ubere nudnou práci — třeba opakované klikání a testování. Rozhodování zůstává na člověku." |
| „Kde ta appka běží? Je to na internetu?" | „Běží u mě na počítači v Dockeru. Nic z toho teď není veřejně na netu." |
| „Vidí to moje soukromá data?" | „V tomhle demu pracuje jen s touhle cvičnou appkou. Co pomocník smí, se dá přesně omezit." |
| „Kolik to stojí / co k tomu potřebuju?" | „Nástroj (Claude Code) a přípojky k prohlížeči a dokumentaci. Detaily nejsou téma dneška — dneska ukazuju, co to umí." |
| „Můžu si to zkusit taky?" | „Jasně, appka je malá a celý postup je popsaný v `README.md` tohoto projektu." |

---

## 7. Když něco selže (fallbacky — měj je nachystané)

Živé demo občas zlobí. Klid a plán B:

- **Aplikace nejede (ne HTTP 200):**
  `cd task-library && docker compose up -d`, počkej pár vteřin, refresh. Pak pokračuj.
- **`/mcp` neukazuje servery připojené:** zavři a znovu otevři Claude Code v tomto projektu;
  detailní diagnostika je v `README.md`, sekce 3 (a `/doctor`).
- **Pomocník se v prohlížeči „zasekne":** nevadí, řekni divákovi „zkusíme to znovu" a pošli
  prompt ještě jednou — je normální, že se občas zopakuje krok.
- **Úplný výpadek prohlížeče:** máš záchranu — ukaž **předchozí screenshot**
  (`task-library-overeni.png` v kořeni projektu vzniká při ověření) a vysvětli, co na něm je.
- **Zlobí Context7 (Akt 3):** je volitelný, klidně ho vynech. Hlavní pointa je Akt 1.

> 🛟 **Zlaté pravidlo:** kdyby zlobilo cokoli, vrať se k jediné větě —
> „AI si samo otevřelo prohlížeč a otestovalo aplikaci." To je to, co si má divák zapamatovat.

---

## 8. Na co si dát pozor (časté chyby prezentujícího)

- **Nezahlcuj pojmy.** „MCP", „stdio", „HTTP transport", „accessibility tree" — do publika laiků nepatří.
- **Neukazuj kód**, dokud o něj nikdo nepožádá. Předvádíš schopnost, ne zdrojáky.
- **Mluv, když to běží.** Ticho během automatizace vypadá, že se „něco pokazilo". Komentuj každý krok.
- **Nepřeháněj.** Neříkej „nahradí lidi" / „nikdy se neplete". Řekni, co reálně vidíš na obrazovce.
- **Měj vše rozběhnuté předem.** Nikdy nespouštěj `docker compose up --build` poprvé před divákem.

---

## 9. Ultra-krátká verze (když máš jen 3 minuty)

1. Ukaž appku na `http://localhost:8080` (10 s): „seznam úkolů".
2. Pošli kratší prompt z sekce 5 a nech pomocníka appku proklikat (2 min). Komentuj.
3. Ukaž screenshot a řekni pointu: „Otestoval mi appku sám, bez psaní testů." (30 s)

Hotovo. Zbytek (audit log, Context7) je bonus, když je zájem a čas.

---

> Tento návod patří k **Dílu 04 tutoriálu Claude Code — MCP servery**. Technické pozadí
> je v `README.md`, architektura cvičné aplikace v `ARCHITECTURE.md`.
