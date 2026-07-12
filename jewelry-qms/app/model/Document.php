<?php
declare(strict_types=1);

namespace app\model;

class Document extends BaseModel
{
    protected $name = 'documents';

    protected $displayField = 'title';

    public function docCategory()
    {
        return $this->belongsTo(DocCategory::class, 'category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function docTemplate()
    {
        return $this->belongsTo(DocTemplate::class, 'template_id');
    }

    public function documentRevisions()
    {
        return $this->hasMany(DocumentRevision::class);
    }
}
