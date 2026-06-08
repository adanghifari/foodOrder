<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ChatbotMetric extends Model
{
    protected $collection = 'chatbot_metrics';

    protected $primaryKey = '_id';

    protected $fillable = [
        'user_id',
        'source',
        'intent_rule_based',
        'intent_resolved',
        'ai_decision',
        'ai_confidence',
        'action',
        'latency_ms',
        'channel',
    ];
}
