# Architektur- & Refactoring-Guide: KI, Namespaces & Theming

Dieses Dokument hält die vollumfänglichen Architektur-Standards und Refactoring-Richtlinien fest, die in den Phasen 15 bis 32 im Projekt etabliert wurden ("Seelenfunke Domain-Driven Restructuring"). 

Es dient als direktes Master-Handbuch für zukünftige Restrukturierungen verbleibender Systembereiche wie dem **Bestellwesen (Orders)**, der **Buchhaltung (Accounting)** oder dem **Personalwesen (HR)**.

---

## 1. Guideline: KI-Traits & Werkzeuge (Ai...Funcs)

**Das Problem von früher:** Riesige monolithische Traits (z. B. `AiScoutFuncs`) mit Dutzenden "generic" Funktionen wie `check_status()`, die den Agenten extrem verwirrt und zu schwerwiegenden Fehleraufrufen (Halluzinationen) geführt haben.

**Die saubere, neue Architektur (Domain-Driven):**
1. **Separation of Concerns (Kapselung):**
   Für jeden logischen Kontext wird ein dediziertes Trait unter `app/Services/AI/Functions/` erstellt. 
   *Beispiel:* `AiProductAnalyticsFuncs.php`, `AiProductFractureFuncs.php`, `AiOrderReturnsFuncs.php`.
2. **Domain-Level Namespace-Prefixing:**
   Jedes KI-Werkzeug *muss* den Namen seiner Model-Domain als striktes Präfix tragen! 
   *Schlecht:* `get_overview()` oder `update_status()`
   *Perfekt:* `order_return_get_overview()` oder `order_return_update_status()`.
   Dies hilft dem LLM beim Function-Calling ohne Zweideutigkeiten ("Welcher Status von welcher Tabelle soll geupdated werden?").
3. **Backend-Registrierung & Seeding:**
   - Jedes neue Trait muss nahtlos in der `AIFunctionsRegistry.php` (unter `getAvailableFunctions()`) eingehangen werden.
   - Die Zuweisung, welcher KI-Agent Zugriff auf diese Tools hat, geschieht ausschließlich über `database/seeders/AiAgentSeeder.php`. Ein anschließendes `php artisan db:seed --class=AiAgentSeeder` verbindet die KI mit dem neuen Werkzeug-Arsenal.

---

## 2. Guideline: Livewire Komponenten & Ordner-Logik

Das System muss im Code genauso logisch aufgeteilt sein, wie die Abteilungen in der Organigramm-Sicht in der Firmenleitung.

**Richtlinien für den Refactoring-Flow:**
1. **Ordnerstruktur (Backend & Frontend):** 
   Verschiebe alle Livewire-PHP Dateien der jeweiligen Domäne in exakte Unterordner (z.B. von `app/Livewire/Shop/` nach `app/Livewire/Shop/Order/`). Dasselbe gilt für die Blade-Views unter `resources/views/livewire/shop/order/`.
2. **"Abteilungsname_Komponente" Naming Convention:**
   Keine global umherfliegenden Begriffe mehr. 
   - *Alt:* `ShopNewsletter.php`, `Voucher.php`, `ProductAnalytics.php`
   - *Neu:* `MarketingNewsletter.php`, `MarketingVoucher.php`, `OrderOverview.php`, `OrderReturns.php`. 
3. **Kaskadierendes Aufräumen:**
   Nach dem Verschieben und Umbenennen zwingend sicherstellen:
   - Die `routes/partials/admin_routes.php` anpassen.
   - Im Admin-Backend Container (`resources/views/backend/admin/pages/`) die `@livewire('shop.order.order-overview')` Einbindungen aktualisieren.
   - Das alte Verzeichnis löschen.

---

## 3. Guideline: Dynamic Department Theming (Farb-Vererbung)

Der "Wow"-Effekt für die Nutzer: Die gesamte Benutzeroberfläche einer Abteilung (Buttons, Icons, Tabellen-Ränder, Glow-Effekte) folgt dynamisch der Farbe, die dem `AiDepartment` (der Abteilung) im Organigramm vergeben wurde. Keine hartkodierten `text-primary` oder festen Tailwind-Styles mehr.

Dies erfordert 4 exakte Implementierungsschritte für **jede einzelne** Livewire Komponente der Domäne.

### Schritt 3.1: Das PHP-Trait einbinden
In der Livewire-Component Class (`app/Livewire/Shop/Order/OrderOverview.php`) fügen wir das Trait ein und definieren das Ziel-Department:

```php
namespace App\Livewire\Shop\Order;

use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

class OrderOverview extends Component
{
    use WithDepartmentTheming;

    // Definiert den EXAKTEN String der Abteilung in der Datenbank ('Marketing', 'Produkte', 'Bestellungen')
    protected string $themingDepartment = 'Bestellungen'; 
    
    // ...
}
```

### Schritt 3.2: Blade-Root Wrapper injizieren
Springe in das dazugehörige `.blade.php` Template. Suche den allerersten umschließenden `<div ...>` Container der Komponente und füge **zwingend** den folgenden CSS-Variablen-Block in das `style`-Attribut ein. (Dieser Block stellt alle Opacity-Prozente nativ bereit, was für sauberes Tailwind-JIT Rendering notwendig ist).

```html
<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="...">
    <!-- Content -->
</div>
```

### Schritt 3.3: Statische Tailwind CSS-Klassen "dynifizieren"
Durchsuche per Suchen & Ersetzen das gesamte Blade-Template nach alten `primary` Klassen oder anderweitig fixierten Farben.

- **Vollfarbe:**
  `text-primary` ➔ `text-[var(--theme-color)]`
  `bg-primary` ➔ `bg-[var(--theme-color)]`
  `border-primary` ➔ `border-[var(--theme-color)]`
  
- **Opacity / Deckkraft (WICHTIG!):**
  Nutze *niemals* die Standard Tailwind Slice-Syntax (`bg-[var(--theme-color)]/20`), da dies am CSS-Variablen Parser für reine Hex-Codes scheitert. Verwende immer die hart angelegte Hex-Mischung Variablen-Syntax aus Schritt 3.2!
  `bg-primary/20` ➔ `bg-[var(--theme-color-20)]`
  `text-primary/10` ➔ `text-[var(--theme-color-10)]`
  `border-primary/50` ➔ `border-[var(--theme-color-50)]`

- **Sonderfall: Checkboxen/Inputs**
  `focus:ring-primary` ➔ `focus:ring-[var(--theme-color)]`
  `peer-checked:bg-primary` ➔ `peer-checked:bg-[var(--theme-color)]`

- **Sonderfall: Hover Text Visibility Bug**
  Pass auf Buttons mit dynamischer Füllfarbe auf! 
  *Gefahr:* Ein Button hat `bg-[var(--theme-color)] text-gray-900`. Beim Hover soll er weiß werden (`hover:bg-white`). Steht dann im Alt-Code oft noch ein schlampig vererbtes `hover:text-white`, wird der Text unsichtbar! Entferne also `hover:text-white`.

### Schritt 3.4: Admin Navigation koppeln
Im Menübaum (`resources/views/backend/admin/livewire/admin-navigation.blade.php`) werden Sub-Einträge generiert. Hier das Theme-Color Array (`$departmentsLevel1`) anzapfen und an die Blade-Component weiterreichen.

Beispiel für das Navigations-Icon der Kinderseiten:
```html
<x-heroicon-o-shopping-bag class="w-4 h-4 text-[var(--theme-color)] drop-shadow-[0_0_8px_currentColor]" />
```

---

*Ende der Dokumentation. Der Agent hat diesen Guide als Basiswissen für alle anstehenden Modularisierungs-Aktivitäten von Abteilungen integriert.*
