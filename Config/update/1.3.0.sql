ALTER TABLE attribute_type
  ADD   `image_max_width` FLOAT AFTER `step`,
  ADD   `image_max_height` FLOAT AFTER `image_max_width`,
  ADD   `image_ratio` FLOAT AFTER `image_max_height`;