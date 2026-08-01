<?php

namespace App\Services\Printing;

/**
 * Sinh chuỗi lệnh ESC/POS thô cho máy in nhiệt (khổ 58mm = 32 ký tự,
 * khổ 80mm = 48 ký tự). Không phụ thuộc thư viện ngoài — đủ dùng cho
 * hóa đơn: căn lề, đậm, phóng to, kẻ dòng, cắt giấy, mở két.
 */
class EscPosBuilder
{
    private const ESC = "\x1b";

    private const GS = "\x1d";

    private string $buffer = '';

    public function __construct(private int $width = 32) {}

    public static function for58mm(): self
    {
        return new self(32);
    }

    public static function for80mm(): self
    {
        return new self(48);
    }

    public function initialize(): self
    {
        $this->buffer .= self::ESC.'@';

        return $this;
    }

    public function align(string $mode): self
    {
        $map = ['left' => 0, 'center' => 1, 'right' => 2];
        $this->buffer .= self::ESC.'a'.chr($map[$mode] ?? 0);

        return $this;
    }

    public function bold(bool $on = true): self
    {
        $this->buffer .= self::ESC.'E'.chr($on ? 1 : 0);

        return $this;
    }

    /** Phóng to gấp đôi chiều cao + rộng (tiêu đề hóa đơn). */
    public function doubleSize(bool $on = true): self
    {
        $this->buffer .= self::GS.'!'.chr($on ? 0x11 : 0x00);

        return $this;
    }

    public function text(string $text): self
    {
        // Máy in nhiệt phổ biến tại VN không hỗ trợ UTF-8 → chuyển về không dấu
        $this->buffer .= $this->stripAccents($text)."\n";

        return $this;
    }

    /** Dòng 2 cột: trái (tên món) + phải (thành tiền), tự cắt cho vừa khổ giấy. */
    public function row(string $left, string $right): self
    {
        $left = $this->stripAccents($left);
        $right = $this->stripAccents($right);

        $maxLeft = $this->width - strlen($right) - 1;

        if ($maxLeft < 1) {
            return $this->text($left.' '.$right);
        }

        if (strlen($left) > $maxLeft) {
            $left = substr($left, 0, $maxLeft - 1).'.';
        }

        $this->buffer .= str_pad($left, $maxLeft).' '.$right."\n";

        return $this;
    }

    public function divider(string $char = '-'): self
    {
        $this->buffer .= str_repeat($char, $this->width)."\n";

        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->buffer .= self::ESC.'d'.chr(max(1, min(255, $lines)));

        return $this;
    }

    public function cut(): self
    {
        $this->buffer .= self::GS.'V'.chr(66).chr(3);

        return $this;
    }

    /** Mở két tiền nối qua cổng RJ11 của máy in. */
    public function openCashDrawer(): self
    {
        $this->buffer .= self::ESC.'p'.chr(0).chr(25).chr(250);

        return $this;
    }

    public function getBytes(): string
    {
        return $this->buffer;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    /** Chuyển tiếng Việt có dấu về không dấu (charset máy in nhiệt phổ thông). */
    private function stripAccents(string $text): string
    {
        $accents = [
            'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
            'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
            'ì', 'í', 'ị', 'ỉ', 'ĩ',
            'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
            'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
            'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ', 'đ',
            'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
            'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
            'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
            'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
            'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
            'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ', 'Đ',
        ];
        $replacements = [
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y', 'd',
            'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
            'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
            'I', 'I', 'I', 'I', 'I',
            'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
            'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
            'Y', 'Y', 'Y', 'Y', 'Y', 'D',
        ];

        return str_replace($accents, $replacements, $text);
    }
}
