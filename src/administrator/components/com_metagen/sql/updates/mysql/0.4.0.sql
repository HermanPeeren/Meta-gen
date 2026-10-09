-- A metalanguage no longer has to fit in 64 KB.
--
-- `text` holds 65,535 bytes. ER1 and LionCore M3 are a few kilobytes, so the
-- limit was never reached by anything written here. A language read in from
-- LionWeb is a different size: JCB's is 139 language entities and 273 KB, and
-- storing it failed with "Data too long for column 'form_data'".
--
-- `mediumtext` holds 16 MB, which is the next size up and the one every other
-- Joomla component reaches for when a column holds a document rather than a
-- sentence.

ALTER TABLE `#__metagen_metalanguages`
    MODIFY `form_data` mediumtext COLLATE utf8mb4_unicode_ci;
