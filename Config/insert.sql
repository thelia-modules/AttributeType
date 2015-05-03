INSERT INTO `attribute_type` (`id`, `slug`, `has_attribute_av_value`, `is_multilingual_attribute_av_value`, `pattern`, `css_class`, `input_type`, `max`, `min`, `step`, `created_at`, `updated_at`) VALUES
(1, ''color'', 1, 0, ''#([a-f]|[A-F]|[0-9]){3}(([a-f]|[A-F]|[0-9]){3})?\\\\b'', NULL, ''color'', NULL, NULL, NULL, ''2015-05-02 21:24:06'', ''2015-05-02 21:24:06'');

INSERT INTO `attribute_type_i18n` (`id`, `locale`, `title`, `description`) VALUES
(1, 'cs_CZ', 'barva', 'hexadecimální barva'),
(1, 'en_US', 'Color', 'Color hexadecimal'),
(1, 'es_ES', 'Color', 'Color hexadecimal'),
(1, 'fr_FR', 'Couleur', 'Couleur hexadécimal'),
(1, 'it_IT', 'Colore', 'Colore esadecimale'),
(1, 'ru_RU', 'цвет', 'шестнадцатеричное цвета');