<?php

namespace App\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberExportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->effectiveStatus(),
            'total' => $this->total,
            'processed' => $this->processed,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
        ];
    }
}
