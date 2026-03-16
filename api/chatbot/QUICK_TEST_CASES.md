# Quick Intent Test Cases

| STT | Mẫu câu | Intent mong đợi |
|---|---|---|
| 1 | xin chào | greeting |
| 2 | hello bạn | greeting |
| 3 | hi chatbot | greeting |
| 4 | còn phòng trống không | search_available_room |
| 5 | hiện tại còn phòng không | search_available_room |
| 6 | available room không | search_available_room |
| 7 | phòng dưới 3 triệu | search_room_by_price |
| 8 | phòng giá 2tr | search_room_by_price |
| 9 | phòng bao nhiêu tiền | search_room_by_price |
| 10 | giá điện nước bao nhiêu | utility_price |
| 11 | tiền điện tháng này | utility_price |
| 12 | internet bao nhiêu | utility_price |
| 13 | phòng A101 còn không | room_detail |
| 14 | cho xem phòng B203 | room_detail |
| 15 | tôi muốn hỏi thông tin khác | unknown |

## Cách test nhanh bằng Postman

1. POST `/api/chatbot/chat.php`
2. Body JSON: `{"message":"phòng dưới 3 triệu"}`
3. Kỳ vọng: phản hồi theo dữ liệu DB, không bịa dữ liệu.

## Lưu ý

- Intent `unknown` là bình thường khi câu không khớp keyword.
- Bot chỉ trả dữ liệu có trong database.