<?php
declare(strict_types=1);

namespace app\model;

class QmsAgentSuggestion extends BaseModel
{
    protected $name = 'qms_agent_suggestions';

    public function element()
    {
        return $this->belongsTo(QmsElement::class, 'element_id');
    }
}
