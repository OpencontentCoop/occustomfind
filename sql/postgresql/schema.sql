CREATE TABLE ocopendatadataset
(
  repository   varchar(255) not null default 0,
  guid   varchar(255) not null default 0,
  created_at bigint default 0,
  modified_at bigint default 0,
  creator integer default 0,
  data text
);

ALTER TABLE ONLY ocopendatadataset
  ADD CONSTRAINT ocopendatadataset_pkey PRIMARY KEY (repository, guid);
