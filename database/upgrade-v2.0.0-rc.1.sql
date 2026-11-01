-- EP-DI v2.0.0-rc.1 — Semantic News / SEO / Trust Infrastructure

CREATE TABLE IF NOT EXISTS ep_authors (
 author_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 job_title VARCHAR(190) NULL,
 beat VARCHAR(190) NULL,
 bio TEXT NULL,
 photo_url VARCHAR(700) NULL,
 public_email VARCHAR(190) NULL,
 same_as_json LONGTEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(author_guid),
 UNIQUE KEY uk_author_slug(tenant_guid,slug),
 KEY ix_author_status(tenant_guid,status,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_topics (
 topic_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NULL,
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 description TEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(topic_guid),
 UNIQUE KEY uk_topic_slug(tenant_guid,slug),
 KEY ix_topic_status(tenant_guid,status,name),
 KEY ix_topic_publication(tenant_guid,publication_guid,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_entities (
 entity_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 entity_type VARCHAR(32) NOT NULL DEFAULT 'OTHER',
 name VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL,
 description TEXT NULL,
 canonical_url VARCHAR(700) NULL,
 same_as_json LONGTEXT NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(entity_guid),
 UNIQUE KEY uk_entity_slug(tenant_guid,slug),
 KEY ix_entity_type_status(tenant_guid,entity_type,status,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_articles (
 article_guid BINARY(16) NOT NULL,
 tenant_guid BINARY(16) NOT NULL,
 publication_guid BINARY(16) NOT NULL,
 city_guid BINARY(16) NULL,
 language_guid BINARY(16) NULL,
 section_guid BINARY(16) NULL,
 edition_guid BINARY(16) NULL,
 page_guid BINARY(16) NULL,
 title VARCHAR(250) NOT NULL,
 slug VARCHAR(220) NOT NULL,
 deck TEXT NULL,
 body LONGTEXT NOT NULL,
 primary_image_url VARCHAR(700) NULL,
 translation_key VARCHAR(190) NULL,
 source_notes TEXT NULL,
 correction_note TEXT NULL,
 ai_assisted TINYINT(1) NOT NULL DEFAULT 0,
 ai_disclosure VARCHAR(700) NULL,
 status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
 published_at DATETIME(6) NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 created_by_user_guid BINARY(16) NULL,
 updated_by_user_guid BINARY(16) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 PRIMARY KEY(article_guid),
 UNIQUE KEY uk_article_slug(tenant_guid,slug),
 KEY ix_article_status_published(tenant_guid,status,published_at),
 KEY ix_article_publication_status(tenant_guid,publication_guid,status,published_at),
 KEY ix_article_edition_page(tenant_guid,edition_guid,page_guid),
 KEY ix_article_translation(tenant_guid,translation_key,status),
 FULLTEXT KEY ft_article_content(title,deck,body)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_article_authors (
 tenant_guid BINARY(16) NOT NULL,
 article_guid BINARY(16) NOT NULL,
 author_guid BINARY(16) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 PRIMARY KEY(article_guid,author_guid),
 KEY ix_article_author_lookup(tenant_guid,author_guid,article_guid),
 KEY ix_article_author_order(tenant_guid,article_guid,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_article_topics (
 tenant_guid BINARY(16) NOT NULL,
 article_guid BINARY(16) NOT NULL,
 topic_guid BINARY(16) NOT NULL,
 PRIMARY KEY(article_guid,topic_guid),
 KEY ix_article_topic_lookup(tenant_guid,topic_guid,article_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_article_entities (
 tenant_guid BINARY(16) NOT NULL,
 article_guid BINARY(16) NOT NULL,
 entity_guid BINARY(16) NOT NULL,
 PRIMARY KEY(article_guid,entity_guid),
 KEY ix_article_entity_lookup(tenant_guid,entity_guid,article_guid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
