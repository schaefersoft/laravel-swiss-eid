# Changelog

## [0.5.0](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.4.2...v0.5.0) (2026-08-04)


### Features

* add trust_anchors as alternative to accepted issuer DIDs ([13c9072](https://github.com/schaefersoft/laravel-swiss-eid/commit/13c907284e6fc852a66824858833a1f8b291e357))
* add verification_purpose support for vqPS registration ([bc63d09](https://github.com/schaefersoft/laravel-swiss-eid/commit/bc63d095412304d3196dc4e2b03f1ab716aa23b3))
* check minimum swiyu-verifier version 4.1.2 in doctor command ([228100d](https://github.com/schaefersoft/laravel-swiss-eid/commit/228100d3e619a521e8d4008ca61018fa9fcbf185))
* support multiple vct values for the requested credential type ([7ab8b5b](https://github.com/schaefersoft/laravel-swiss-eid/commit/7ab8b5b9c64c09c677180164d50a5510a63d6502))
* warn on deprecated did:tdw issuers and document did:webvh onboarding ([1419d68](https://github.com/schaefersoft/laravel-swiss-eid/commit/1419d68aa9c1bea9678586726576d26cd704d664))


### Bug Fixes

* default response_mode to direct_post.jwt as enforced by wallets since CD-004 ([f0a781f](https://github.com/schaefersoft/laravel-swiss-eid/commit/f0a781f4cba1656ab54ec8c2d007391fd501a5c1))
* mark verification expired when verifier returns 404 on result fetch ([7e152eb](https://github.com/schaefersoft/laravel-swiss-eid/commit/7e152eb0d628ab8a60e40c4450372b11dc0aa205))

## [0.4.2](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.4.1...v0.4.2) (2026-08-04)


### Bug Fixes

* bump guzzle to patched version resolving security advisories ([ab8f9b0](https://github.com/schaefersoft/laravel-swiss-eid/commit/ab8f9b0d799a67ad3d134a19ccbfe512aaa778aa))
* bump guzzle to patched version resolving security advisories ([91dc753](https://github.com/schaefersoft/laravel-swiss-eid/commit/91dc75347e8892717d83cd43396560b6e085173e))

## [0.4.1](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.4.0...v0.4.1) (2026-06-23)


### Bug Fixes

* correct Beta-ID claim names for birth place and sex ([3627d74](https://github.com/schaefersoft/laravel-swiss-eid/commit/3627d74b825a32b9e5af30ed3728492fd099dc0a))
* dispatch VerificationExpired from a schedulable swiss-eid:expire command ([e6a39a8](https://github.com/schaefersoft/laravel-swiss-eid/commit/e6a39a883f173f1c365a4d2626234965ab8716c5))
* honor the size argument in QrCodeGenerator SVG output ([ecb86e9](https://github.com/schaefersoft/laravel-swiss-eid/commit/ecb86e94042bb6a4c651d4f0631e8c700adc3810))
* make webhook idempotent and acknowledge unknown verification ids ([6bfe25a](https://github.com/schaefersoft/laravel-swiss-eid/commit/6bfe25ae4585c1554a8a7eb4c88a32796cc9f047))
* persist verifier error_code and error_description on verifications ([55e2bd6](https://github.com/schaefersoft/laravel-swiss-eid/commit/55e2bd66bbe64a14ca09674a5fa53bc27e647c78))
* support dot-notation in VerificationResult get and has ([4c04fe9](https://github.com/schaefersoft/laravel-swiss-eid/commit/4c04fe902a8c39eafdb209c27a73a2394db0ec9f))
* wrap OAuth2 token endpoint errors in VerifierClient ([3da9b7a](https://github.com/schaefersoft/laravel-swiss-eid/commit/3da9b7ae358adfa72a8e01a59c3c81f379841264))


### Miscellaneous Chores

* log webhook payload at debug level instead of info ([4856c51](https://github.com/schaefersoft/laravel-swiss-eid/commit/4856c51bdb0797f7756114464a122c323c9e1216))

## [0.4.0](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.3.0...v0.4.0) (2026-05-11)


### Features

* **core:** localizable verficaition states ([6c85141](https://github.com/schaefersoft/laravel-swiss-eid/commit/6c85141baf842d437d717dcf38264165f46be7cb))
* doctor command ([cda387e](https://github.com/schaefersoft/laravel-swiss-eid/commit/cda387e94d18c021beb24bbe78c685b51add9772))


### Bug Fixes

* doctor command test issues ([9bbd185](https://github.com/schaefersoft/laravel-swiss-eid/commit/9bbd18594f6b3afb4af2c0ed3a51ad588d7bc3f9))
* **tests:** code coverage, env usage and config call ([41092d1](https://github.com/schaefersoft/laravel-swiss-eid/commit/41092d1750191937f7ba14fd21397747f5d43616))
* **tests:** fix label tests to localized labels ([36ff404](https://github.com/schaefersoft/laravel-swiss-eid/commit/36ff4044b4e48791b65ea2aa5244c3683d18bc83))


### Miscellaneous Chores

* pint ([34cd8da](https://github.com/schaefersoft/laravel-swiss-eid/commit/34cd8da14049dc0b72c3fa7b77185011547d50ce))

## [0.3.0](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.2.3...v0.3.0) (2026-04-30)


### Features

* **core:** configurable response mode for wallet responses and update related tests, config, and documentation ([7d9d5c2](https://github.com/schaefersoft/laravel-swiss-eid/commit/7d9d5c27326db3c3bd6de7e57f7f8abf7f8e92cc))


### Miscellaneous Chores

* update default credential type from "betaid-sdjwt" to "test-sdjwt" in tests and config ([2f83bf3](https://github.com/schaefersoft/laravel-swiss-eid/commit/2f83bf3137dc02552fde505f5ddd9206ed901d6c))

## [0.2.3](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.2.2...v0.2.3) (2026-04-21)


### Miscellaneous Chores

* add total downloads to readme ([e36af88](https://github.com/schaefersoft/laravel-swiss-eid/commit/e36af886698158e1a116ba1055adcb3145d790d3))

## [0.2.2](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.2.1...v0.2.2) (2026-04-20)


### Miscellaneous Chores

* add pint dependency ([3c25f85](https://github.com/schaefersoft/laravel-swiss-eid/commit/3c25f856dd7508a993a04438a203272d70ad622e))
* run pint ([04a1f28](https://github.com/schaefersoft/laravel-swiss-eid/commit/04a1f287d56be77a02be229e8f3c4355dab38677))

## [0.2.1](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.2.0...v0.2.1) (2026-04-17)


### Bug Fixes

* **core:** support different user_id datatypes for verifications ([7b93523](https://github.com/schaefersoft/laravel-swiss-eid/commit/7b935233ccc4ec32b5e50b589b095cd7c2b5ebfb))

## [0.2.0](https://github.com/schaefersoft/laravel-swiss-eid/compare/v0.1.0...v0.2.0) (2026-04-17)


### Features

* add pint as dev dependency ([1971883](https://github.com/schaefersoft/laravel-swiss-eid/commit/1971883946139b6d831110e75573a76d0a0d34d3))
* automated build tests ([7521a00](https://github.com/schaefersoft/laravel-swiss-eid/commit/7521a007c22745c71185b8a21d76b6bfbee266af))
* automated build tests ([869b309](https://github.com/schaefersoft/laravel-swiss-eid/commit/869b30902b2702cf53d4baea48b92d20d048191b))


### Bug Fixes

* action issues v3 ([4c1d4d3](https://github.com/schaefersoft/laravel-swiss-eid/commit/4c1d4d343c50ddb2394fd8c31df9074ca0504089))
* build tests ([21528c8](https://github.com/schaefersoft/laravel-swiss-eid/commit/21528c876d4de8e57255f4931dd6f2c8cf7d4abb))
* **tests:** fix credentialField test ([cdcf91c](https://github.com/schaefersoft/laravel-swiss-eid/commit/cdcf91c7b626b4ba44d244e8dd2d75be29177db0))


### Miscellaneous Chores

* add guzzle as direct dependency for laravel 10 ([82b829c](https://github.com/schaefersoft/laravel-swiss-eid/commit/82b829c89716b721c217f7af6feb6189b0b2bd3b))
* add support for pest 4 (laravel 13 required) ([901d0b6](https://github.com/schaefersoft/laravel-swiss-eid/commit/901d0b6c4cf447694ef27bd643c8d642eb709ef4))
* adjust phpstan config ([2454531](https://github.com/schaefersoft/laravel-swiss-eid/commit/2454531385c5c6cf2e782e9a4f974e3fde0f9eff))
* adjust test for laravel and php versions ([837db5f](https://github.com/schaefersoft/laravel-swiss-eid/commit/837db5fec668443159e775f1fdf3e3bfb0cb9299))
* fix phpstan errors ([f8cfb08](https://github.com/schaefersoft/laravel-swiss-eid/commit/f8cfb08e1923c0822367968215fac68bc037d98e))
* remove composer .lock from repo ([5287c78](https://github.com/schaefersoft/laravel-swiss-eid/commit/5287c782407dd82bed13cd675a6c37e2c63733b5))
* vendor folder in .gitignore ([d6bd0c6](https://github.com/schaefersoft/laravel-swiss-eid/commit/d6bd0c65ae3897061542ecd1f066b9963deb2fd8))

## Changelog

All notable changes to `schaefersoft/laravel-swiss-eid` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
