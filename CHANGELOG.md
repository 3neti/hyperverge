# Changelog

All notable changes to `3neti/hyperverge` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-11-22

### Added

#### KYC Verification
- HyperVerge Link KYC API integration
- Results API for fetching verification data
- Selfie liveness detection service
- Face matching service for identity verification

#### Face Verification System
- Face enrollment with reference selfie storage
- Face verification against stored references
- Liveness detection for anti-spoofing
- `HasFaceVerification` trait for any model
- Configurable match confidence thresholds
- Verification attempt audit trail
- Face verification statistics and reporting
- Automatic cleanup of old verification attempts
- Events: `FaceEnrolled`, `FaceVerificationSucceeded`, `FaceVerificationFailed`, `ReferenceSelfieUpdated`

#### Document Signing
- PKCS#7 digital signatures for PDFs
- Document tamper detection
- QR code generation for verification URLs
- QR watermarking on signed PDFs
- Verification certificate generation
- Signature stamps with ID card images

#### Blockchain Timestamping
- OpenTimestamps integration for immutable proof
- Automatic timestamping on document signing
- Verification of blockchain timestamps
- Bitcoin blockchain anchoring

#### Credential Management
- Flexible credential resolution system
- Campaign-level credential overrides
- User-level credential overrides
- Environment-based default credentials
- Encrypted storage for user credentials

#### Developer Experience
- Laravel Actions for all operations
- Type-safe DTOs with Spatie Laravel Data
- Comprehensive test suite (33 tests)
- Detailed documentation and guides
- Quick start guides with examples
- Face verification quick start with campaigns

#### Configuration
- Extensive configuration options
- QR code customization
- Document signing settings
- Face verification thresholds
- Liveness detection controls

### Documentation
- Complete README with usage examples
- Face Verification guide
- QR Watermark implementation guide
- Certificate integration guide
- Comprehensive testing guide
- Credential override documentation
- Quick start guides
- Project summary

## [Unreleased]

### Planned
- Dedupe workflow integration (AFIS-like selfie checking)
- Additional HyperVerge workflows
- Enhanced reporting and analytics

---

[1.0.0]: https://github.com/3neti/hyperverge/releases/tag/v1.0.0
