# `templates/components/`

Reusable Twig partials used by the staff-admin UI shell and feature
pages. Every component is invoked with `{% include ... with { ... } only %}`
so callers pass parameters explicitly — no globals, no implicit context.

| Component | Parameters | One-line usage |
| --- | --- | --- |
| `_main_nav.html.twig` | _(no parameters; reads `main_navigation()` from `App\Ui\Twig\NavigationExtension`)_ | `{% include 'components/_main_nav.html.twig' %}` |
| `_kpi_card.html.twig` | `string label`, `string value`, `?string delta` | `{% include 'components/_kpi_card.html.twig' with { label: 'Today\'s Revenue', value: '$4,182.50', delta: '+12% vs. yesterday' } only %}` |
| `_page_header.html.twig` | `string title`, `?string actions` _(pre-rendered HTML, rendered raw)_ | `{% include 'components/_page_header.html.twig' with { title: 'Admin Dashboard', actions: actionsHtml } only %}` |
| `_status_badge.html.twig` | `string status` ∈ `succeeded \| pending \| failed \| refunded` | `{% include 'components/_status_badge.html.twig' with { status: tx.status.value } only %}` |
| `_empty_state.html.twig` | `string title`, `string message`, `?string ctaLabel`, `?string ctaRoute` | `{% include 'components/_empty_state.html.twig' with { title: 'Coming soon', message: 'This screen arrives later.' } only %}` |
| `_breadcrumbs.html.twig` | `array trail` of `{ label, route }` items | `{% include 'components/_breadcrumbs.html.twig' with { trail: [{label:'Dashboard',route:'app_dashboard'},{label:'Reports'}] } only %}` |

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
- **Props:** none — embed the template once on any page that needs the dialog.
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
}
```

### Usage

Embed the dialog once on the page, then call the JS hook from any trigger:

```twig
{# In your page template, after page_body content: #}
{% include 'components/member_lookup_dialog.html.twig' %}
```

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
