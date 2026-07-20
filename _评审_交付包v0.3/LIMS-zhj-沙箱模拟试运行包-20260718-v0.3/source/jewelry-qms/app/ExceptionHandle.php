<?php
namespace app;

use app\exception\FieldAuditException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof FieldAuditException) {
            if ($request->isAjax()) {
                return json(['code' => 500, 'msg' => $e->getMessage()], 500);
            }

            return Response::create(
                '<!doctype html><html lang="zh-CN"><meta charset="utf-8"><title>保存失败</title>'
                . '<body><main style="max-width:720px;margin:80px auto;font-family:sans-serif">'
                . '<h1>本次修改未保存</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a href="javascript:history.back()">返回上一页</a></p></main></body></html>',
                'html',
                500
            );
        }

        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
