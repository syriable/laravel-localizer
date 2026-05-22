<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

final class LivewireDashboard extends Component
{
    public function mount(): void
    {
        session()->flash('status', __('Livewire mounted successfully'));
    }

    public function save(): string
    {
        return trans('livewire.saved');
    }

    public function plural(int $n): string
    {
        return trans_choice('livewire.items', $n);
    }
}
