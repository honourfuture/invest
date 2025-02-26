// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2019  GR缅航网 [   ]
// +----------------------------------------------------------------------
// | 官方网站: http://demo.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | gitee 代码仓库：https://gitee.com/zoujingli/ThinkAdmin
// | github 代码仓库：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

$(function () {

    window.$body = $('body');

    /*! 后台加密登录处理 */
    $body.find('[data-login-form]').map(function (that) {
        that = this;
        require(["md5"], function (md5) {
            $("form").vali(function (data) {
                // data['password'] = md5.hash(md5.hash(data['password']) + data['uniqid']);
                var  xbcloadX = layer.msg('登录中...', { icon: 16 , shade: 0.01 }); 
                $.ajax({ 
                    data: data, type: 'post', url:location.href, 
                    error: function (XMLHttpRequest) {
                        layer.close(xbcloadX);
                        $.msg.tips('E' + XMLHttpRequest.status + ' - 服务器繁忙，请稍候再试！');
                    }, success: function (ret) {
                        layer.close(xbcloadX);
                        if(ret.code == 403) {
                            $(that).find('.googlecode.layui-hide').removeClass('layui-hide');
                        } else if(ret.code == 302) {
                           layer.msg(ret.info);
                        } else if(ret.code == 1) {
                          layer.msg(ret.info, { icon: 1, time: 2000 }, function () {
                              window.location.href = ret.data;
                          });
                        } else {
                            $(that).find('.verify.layui-hide').removeClass('layui-hide');
                            $(that).find('[data-captcha]').trigger('click');
                            layer.msg(ret.info);
                        }
                    }
                });
            });
        });
    });

    /*! 登录图形验证码刷新 */
    $body.on('click', '[data-captcha]', function () {
        var type, token, verify, uniqid, action, $that = $(this);
        action = this.getAttribute('data-captcha') || location.href;
        if (action.length < 5) return $.msg.tips('请设置验证码请求地址');
        type = this.getAttribute('data-captcha-type') || 'captcha-type';
        token = this.getAttribute('data-captcha-token') || 'captcha-token';
        uniqid = this.getAttribute('data-field-uniqid') || 'uniqid';
        verify = this.getAttribute('data-field-verify') || 'verify';
        $.form.load(action, {type: type, token: token}, 'post', function (ret) {
            if (ret.code) {
                $that.html('');
                $that.append($('<img alt="img" src="">').attr('src', ret.data.image));
                $that.append($('<input type="hidden">').attr('name', uniqid).val(ret.data.uniqid));
                if (ret.data.code) {
                    $that.parents('form').find('[name=' + verify + ']').attr('value', ret.data.code);
                } else {
                    $that.parents('form').find('[name=' + verify + ']').attr('value', '');
                }
                return false;
            }
        }, false);
    });

    $('[data-captcha]').map(function () {
        $(this).trigger('click')
    });

});