<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Drumul unui contact printr-un flux: pasul următor și când se trezește. */
#[Fillable(['flow_id', 'contact_id', 'step_id', 'status', 'wake_at', 'trigger_data', 'note', 'started_at', 'finished_at'])]
class FlowRun extends Model
{
    use BelongsToOrganization;

    public $timestamps = false; // started_at / finished_at

    protected function casts(): array
    {
        return ['trigger_data' => 'array', 'wake_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @return BelongsTo<Flow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<FlowStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(FlowStep::class, 'step_id');
    }
}
