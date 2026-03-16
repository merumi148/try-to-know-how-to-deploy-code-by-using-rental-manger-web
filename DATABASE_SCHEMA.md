Database name:
quanlyphongtro_db

Tables schema:

users
- id
- full_name
- email
- phone
- password_hash
- role
- created_at

buildings
- id
- landlord_id
- name
- address

rooms
- id
- building_id
- room_number
- floor
- area
- price
- status
- description

room_images
- id
- room_id
- image_url
- uploaded_at

features
- id
- name

room_features
- room_id
- feature_id

contracts
- id
- tenant_id
- room_id
- start_date
- end_date
- deposit_amount
- status

payments
- id
- contract_id
- month_year
- total_amount
- payment_status

utility_rates
- id
- electricity_price
- water_price
- internet_price
- effective_from

system_rules
- id
- deposit_policy
- notice_period
- min_contract_duration
- allow_pet
- curfew_time

posts
- id
- landlord_id
- room_id
- title
- content
- status

viewing_requests
- id
- customer_name
- phone
- room_id
- preferred_date
- status

chat_logs
- id
- user_message
- bot_response
- detected_intent
- created_at
