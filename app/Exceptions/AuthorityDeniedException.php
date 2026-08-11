<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Người dùng không đủ thẩm quyền cho thao tác phê duyệt.
 *
 * Tách riêng khỏi RuntimeException thường để controller phân biệt được:
 * thiếu thẩm quyền phải trả 403, còn lỗi nghiệp vụ (yêu cầu đã xử lý, dữ liệu
 * không hợp lệ) thì quay lại trang trước kèm thông báo.
 */
class AuthorityDeniedException extends RuntimeException {}
