# `templates/components/`

Reusable Twig partials used by the staff-admin UI shell and feature
pages. Every component is invoked with `{% include ... with { ... } only %}`
so callers pass parameters explicitly — no globals, no implicit context.

| Component | Parameters | One-line usage |
| --- | --- | --- |
| `_main_nav.html.twig` | _(no parameters; reads `main_navigation()` from `App\Ui\Twig\NavigationExtension`)_ | `{% include 'components/_main_nav.html.twig' %}` |
| `_kpi_card.html.twig` | `string icon`, `string value`, `string label`, `?string tint` ∈ `accent \| sage` (defaults to `accent`), `?string deltaText`, `?string deltaTone` ∈ `positive \| negative \| neutral`, `?string note` | `{% include 'components/_kpi_card.html.twig' with { icon: 'money', label: 'Today\'s Revenue', value: '$4,182.50', tint: 'accent', deltaText: '+12%', deltaTone: 'positive', note: 'vs. yesterday' } only %}` |
| `_page_header.html.twig` | `?string breadcrumbs` _(pre-rendered HTML, rendered raw)_, `string title`, `?string subtitle`, `?string actions` _(pre-rendered HTML, rendered raw)_ | `{% include 'components/_page_header.html.twig' with { breadcrumbs: crumbsHtml, title: 'Admin Dashboard', subtitle: 'Welcome back.', actions: actionsHtml } only %}` |
| `_badge.html.twig` | `string label`, `string variant` ∈ `success \| warning \| danger \| info \| neutral`, `?bool outline`, `?string class` | `{% include 'components/_badge.html.twig' with { label: 'Excellent', variant: 'success' } only %}` |
| `_status_badge.html.twig` | `string status` ∈ `succeeded \| pending \| failed \| refunded` _(maps onto `_badge`)_ | `{% include 'components/_status_badge.html.twig' with { status: tx.status.value } only %}` |
| `_empty_state.html.twig` | `string title`, `string message`, `?string ctaLabel`, `?string ctaRoute`, `?string icon` (defaults to `'leaf'`) | `{% include 'components/_empty_state.html.twig' with { title: 'Coming soon', message: 'This screen arrives later.' } only %}` |
| `_breadcrumbs.html.twig` | `array trail` of `{ label, route }` items | `{% include 'components/_breadcrumbs.html.twig' with { trail: [{label:'Dashboard',route:'app_dashboard'},{label:'Reports'}] } only %}` |
| `_icon.html.twig` | `icon(string name, int size = 16, number stroke = 2.75, string class = '')` _(Twig macro — imported, not included)_ | `{% import 'components/_icon.html.twig' as icon %}` then `{{ icon.icon('leaf', 18) }}` |
| `_register_mode_toggle.html.twig` | `string active` ∈ `full \| quick` | `{% include 'components/_register_mode_toggle.html.twig' with { active: 'full' } only %}` |

## Page header subtitle & breadcrumbs

`app.html.twig` exposes two optional slots a feature template can fill:

```twig
{% block page_subtitle %}Build a sale, then take payment.{% endblock %}

{% block breadcrumbs %}
    {% include 'components/_breadcrumbs.html.twig' with {
        trail: [{ label: 'Dashboard', route: 'app_dashboard' }, { label: 'Cash Register' }],
    } only %}
{% endblock %}
```

The subtitle renders under the title inside `_page_header`; breadcrumbs render
as the eyebrow above it, inside the same component. Both are empty by default,
so pages that set neither look unchanged. The breadcrumbs slot is a block
(not a captured string) because a trail is an array, which Twig blocks can't
carry — so the page does the `_breadcrumbs` include itself; `app.html.twig`
then captures the block's rendered HTML and forwards it to `_page_header` as
the `breadcrumbs` parameter, the same way it already does for `page_actions`.

## Icons (`_icon.html.twig`)

A curated SVG line-icon set on a 24x24 grid, drawn from Lucide icon paths at a
2.75 default stroke. Unlike the other components, it is a **Twig macro**, so
import it once per template and call it as a function:

The path geometry is copied from [Lucide](https://lucide.dev) (ISC License,
with an additional MIT sublicense for the icons it inherited from Feather);
see the license text in `_icon.html.twig`'s header comment.

```twig
{% import 'components/_icon.html.twig' as icon %}
{{ icon.icon('bell', 17) }}
{{ icon.icon('leaf', 18, 2.75, 'text-litrec-secondary') }}
```

- Icons inherit color via `stroke="currentColor"` — pass a `text-*` utility in
  the `class` argument to recolor, or a transform utility (e.g. `rotate-90`).
- They are decorative (`aria-hidden="true"`). When an icon is a control's only
  visible content, label the control (`aria-label`), not the icon.
- An unknown name renders an empty, invisible `<svg>` instead of erroring.
- Available names: `search`, `trash`, `plus`, `chevron`, `chevronUp`,
  `chevronR`, `user`, `users`, `cart`, `info`, `bell`, `leaf`, `tree`,
  `calendar`, `heart`, `money`, `tag`, `ticket`, `key`, `arrowUp`, `bolt`,
  `pin`, `check`, `grid`, `clock`, `print`, `printer`, `sun`, `moon`, `minus`,
  `x`, `arrow`, `card`, `gift`, `sliders`, `more`.

## `lr-` component classes

Beyond the Twig partials, the Organic component layer ships as token-driven
CSS classes in `assets/styles/app.css` (under `@layer components`). Compose
them directly in markup; they are global (not scoped to `.lr-screen`) and
recolor automatically with the active theme.

- **Buttons:** `lr-btn` with modifiers `lr-btn-ghost`, `lr-btn-primary`,
  `lr-btn-secondary` (outlined divider-border), `lr-btn-danger`, `lr-btn-lg`,
  `lr-btn-block`, `lr-btn-icon` (36px circle; combine with `lr-btn-lg` for 40px).
- **Cards:** `lr-card` (shadowed data card) + `lr-card-head`, `lr-card-title`,
  `lr-card-body`. `lr-card-context` is the shadow-less sand-surface variant for
  context cards (payer, member/participant, upcoming, facility).
- **Badges:** prefer the `_badge.html.twig` partial; the underlying classes are
  `lr-badge` + a variant (`success`, `warning`, `danger`, `info`, `neutral`)
  plus the optional `outline` modifier.
- **Chips:** `lr-chip`. **Tabs:** `lr-tabs` + `lr-tab` (`is-active`), styled as
  a pill group like `lr-seg`.
- **Icon button:** `lr-iconbtn` (`danger` variant), a circle. **Kbd:** `lr-kbd`.
- **Text helpers:** `lr-muted`, `lr-link`, `lr-num` (tabular figures),
  `lr-row-strong`, `lr-section-label`.
- **Fields:** `lr-field` (label + control wrapper) + `lr-label`. Inputs come in
  two consistent variants: `lr-input` is the wrapper that hosts leading/trailing
  icon slots (`.ico`) around a borderless `<input>` (add `is-focus` for the
  focused look); `litrec-input` is the single-element variant for
  Symfony-form-bound controls. `lr-select` for selects; `lr-check` + `.box` for
  checkboxes.
- **Tables:** `lr-table` (uppercase header cells, density-token row padding,
  hover row, no border on the last row).
- **Lists:** `lr-list` + `lr-list-row` (divider list).
- **Dashboard:** `lr-kpi-flat` (flat card + `lr-kpi-flat-icon` tinted circle, `-label`, `-value`, `-delta`/`-delta-pill`), `lr-dot` (status dot), `lr-datebadge` (circular date badge, `.d`/`.m`), `lr-avatar-sm` (30px sage initials circle).
- **Cash Register:** `lr-seg` (Full/Quick segmented toggle), `lr-totals`
  (`.row`/`.row.total`), `lr-pillradio` + `lr-pillradio-row` (Participant pill
  radio rows), `lr-programrow` (`is-selected`) (program search result rows),
  `lr-kindicon` (`info`/`success`/`warning`/`neutral`, matching `_badge`'s
  variants — sale line-item kind icon).

Do not transition `var()`-backed color/background/border on these classes — a
theme switch would strand the old value (see the note in `app.css`).

## Conventions

- Components are presentation-only — no service calls, no business logic.
- Optional parameters always have an explicit `{% set foo = foo|default(null) %}`
  at the top so callers can omit them without raising `UndefinedVariable`.
- The `_main_nav` component is the one exception that reads from a Twig
  function instead of receiving parameters; its source of truth is
  `App\Ui\Navigation\MainNavigation` and the structure is intentionally
  centralised, not passed through every render.

## Member Lookup Dialog (LRA-46)

Reusable HTMX/Alpine dialog for picking a household member from anywhere in
the staff-admin UI. The component renders the shared `_modal.html.twig`
shell, a five-field filter form (`code`, `lastName`, `firstName`, `phone`,
`email`), and an HTMX-swappable results region backed by
`GET /admin/users/_lookup` (route name: `member_lookup_search`).

- **Import path:** `components/member_lookup_dialog.html.twig`
- **Props:** none — `app.html.twig` embeds the template once for every page
  (LRA-187). Do not include it again from a page template: a second copy
  produces duplicate `#member-lookup-host` islands that both react to
  `member-lookup-open` and duplicate ids.
- **Results partial:** `components/member_lookup_dialog/_results.html.twig`
  (rendered by `MemberLookupController` into `#member-lookup-results`).

### Exposed events

The dialog dispatches a one-shot `member-selected` `CustomEvent` on `window`
when a row is clicked, then closes itself. The `detail` shape is:

```js
{
    memberId: '019571bf-…',     // UUID v7 of the member
    householdId: '019571bf-…',  // UUID v7 of the owning household
    fullName: 'Alice Smith',    // display name from the read model
    code: 'M000010',            // member code
    detailUrl: '/admin/users/019571bf-…/019571bf-…', // member_detail route
}
```

### Usage

The dialog is already embedded once by `app.html.twig`; just call the JS hook
from any trigger:

```html
<button
    type="button"
    onclick="openMemberLookup({
        onSelected: function (payload) {
            console.log('selected', payload.memberId, payload.fullName);
        }
    })"
>
    Pick a member
</button>
```

`openMemberLookup` subscribes the callback to `member-selected` for exactly
one fire and then unsubscribes, so callers do not need to manage listeners.
Focus, ESC, backdrop close, and focus return are inherited from the shared
modal shell.
