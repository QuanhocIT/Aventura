<?php

namespace App\Services;

/**
 * Phân tích cảm xúc phản hồi khách hàng bằng lexicon tiếng Việt (từ điển từ khóa
 * tích cực/tiêu cực theo ngữ cảnh F&B), có xử lý phủ định đơn giản (vd "không ngon").
 *
 * Đây là kỹ thuật lexicon-based sentiment scoring thật (không phải mock) — độ chính
 * xác thấp hơn mô hình ML huấn luyện riêng, nhưng chạy được ngay không cần dữ liệu
 * huấn luyện hay dịch vụ bên ngoài, phù hợp làm bước đầu gắn cờ sentiment tự động.
 */
class SentimentAnalysisService
{
    private const POSITIVE_WORDS = [
        'ngon', 'tuyệt vời', 'tuyệt', 'xuất sắc', 'hài lòng', 'tốt', 'nhanh', 'thân thiện',
        'nhiệt tình', 'chu đáo', 'sạch sẽ', 'thơm ngon', 'thơm', 'đẹp', 'ổn', 'thích',
        'ấn tượng', 'chuyên nghiệp', 'dễ thương', 'vui vẻ', 'thoải mái', 'đáng tiền',
        'giá tốt', 'ưng ý', 'sẽ quay lại', 'quay lại', 'ngon miệng', 'phục vụ tốt',
        'chất lượng', 'tận tâm', 'chu đáo', 'niềm nở', 'lịch sự', 'giá cả hợp lý',
    ];

    private const NEGATIVE_WORDS = [
        'không ngon', 'không sạch', 'không hài lòng', 'không quay lại',
        'tệ', 'dở', 'chán', 'thất vọng', 'chậm', 'quá lâu', 'bẩn', 'dơ', 'ôi thiu', 'thiu',
        'nguội', 'mặn', 'nhạt', 'khó ăn', 'khó chịu', 'thái độ', 'vô lễ', 'hỗn láo',
        'đắt', 'chặt chém', 'lừa đảo', 'gian lận', 'thiếu', 'mất vệ sinh',
        'ồn ào', 'chật chội', 'hôi', 'kém', 'tồi tệ', 'phàn nàn', 'khiếu nại', 'bực mình',
    ];

    private const NEGATION_WORDS = ['không', 'chẳng', 'chả', 'chưa', 'đâu có'];

    /**
     * @return array{sentiment: string|null, score: float|null}
     */
    public function analyze(?string $content): array
    {
        $content = trim((string) $content);

        if ($content === '') {
            return ['sentiment' => null, 'score' => null];
        }

        $normalized = mb_strtolower($content, 'UTF-8');
        $score = 0;
        $matches = 0;

        foreach (self::NEGATIVE_WORDS as $word) {
            $count = $this->countOccurrences($normalized, $word);
            if ($count > 0) {
                $score -= $count;
                $matches += $count;
            }
        }

        foreach (self::POSITIVE_WORDS as $word) {
            $count = $this->countOccurrences($normalized, $word);
            if ($count === 0) {
                continue;
            }

            $matches += $count;
            $score += $this->isNegated($normalized, $word) ? -$count : $count;
        }

        if ($matches === 0) {
            return ['sentiment' => 'neutral', 'score' => 0.0];
        }

        $normalizedScore = max(-1.0, min(1.0, $score / $matches));

        $label = match (true) {
            $normalizedScore > 0.15 => 'positive',
            $normalizedScore < -0.15 => 'negative',
            default => 'neutral',
        };

        return ['sentiment' => $label, 'score' => round($normalizedScore, 2)];
    }

    private function countOccurrences(string $haystack, string $needle): int
    {
        // substr_count() hoạt động đúng trên chuỗi UTF-8 hợp lệ dù không phải hàm mb_*
        // (không có mb_substr_count trong PHP) — chỉ đếm khớp chuỗi byte, không cắt giữa ký tự.
        return substr_count($haystack, $needle);
    }

    /**
     * Kiểm tra xem có từ phủ định ngay trước $word trong phạm vi ~12 ký tự không,
     * để đảo dấu các cụm như "không ngon" khi "ngon" tự nó là từ tích cực.
     */
    private function isNegated(string $content, string $word): bool
    {
        $pos = mb_strpos($content, $word, 0, 'UTF-8');

        if ($pos === false) {
            return false;
        }

        $start = max(0, $pos - 12);
        $before = mb_substr($content, $start, $pos - $start, 'UTF-8');

        foreach (self::NEGATION_WORDS as $neg) {
            if (mb_strpos($before, $neg, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }
}
