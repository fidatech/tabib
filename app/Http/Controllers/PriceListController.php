<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** The price list of the clinic: what is charged besides the visit (a dressing, an ECG, a test ...). */
class PriceListController extends CrudController
{
    protected string $model = Service::class;

    protected string $route = 'services';

    protected string $permission = 'billing';

    protected string $title = 'Price list';

    protected string $singular = 'Service';

    protected string $icon = 'tags';

    protected array $search = ['name', 'name_local'];

    protected string $orderBy = 'name';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150']],
            ['name' => 'name_local', 'label' => 'Name in Dari or Pashto', 'rules' => ['nullable', 'string', 'max:150']],
            ['name' => 'category', 'label' => 'Kind', 'type' => 'select', 'options' => array_map(fn ($c) => __($c), Service::CATEGORIES), 'rules' => ['required', Rule::in(array_keys(Service::CATEGORIES))], 'default' => 'procedure'],
            ['name' => 'price', 'label' => 'Price', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:0', 'max:9999999999']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Service $s) => $s->label(),
            'Kind' => fn (Service $s) => __(Service::CATEGORIES[$s->category] ?? $s->category),
            'Price' => fn (Service $s) => money($s->price, setting('currency')),
            'In use' => fn (Service $s) => $s->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return InvoiceItem::where('service_id', $record->id)->exists() ? __('This service is on invoices. Switch it off instead.') : null;
    }
}
