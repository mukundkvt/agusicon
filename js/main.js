/* ============================================================
   Ortho Edge – Main JavaScript
   ============================================================ */

document.addEventListener("DOMContentLoaded", () => {
  const getLocalServerUrlHint = () => {
    if (window.location.protocol !== "file:") return "";

    const normalizedPath = window.location.pathname.replace(/\\/g, "/");
    const htdocsMarker = "/htdocs";
    const htdocsIndex = normalizedPath.toLowerCase().indexOf(htdocsMarker);

    if (htdocsIndex === -1) {
      return "";
    }

    return `http://localhost${normalizedPath.slice(htdocsIndex + htdocsMarker.length)}`;
  };

  const fileProtocolSubmitMessage = (() => {
    const hint = getLocalServerUrlHint();
    if (hint) {
      return `This form cannot submit while the page is opened as a local file. Please open it through XAMPP/Apache instead: ${hint}`;
    }

    return "This form cannot submit while the page is opened as a local file. Please open it through XAMPP/Apache using http://localhost/...";
  })();

  /* ----------------------------------------------------------
     1. NAVBAR – scroll shrink + active link
  ---------------------------------------------------------- */
  const navbar = document.getElementById("navbar");
  const navToggle = document.querySelector(".nav-toggle");
  const navMobile = document.querySelector(".nav-mobile");
  const navMobileClose = document.querySelector(".nav-mobile-close");

  function updateNav() {
    if (window.scrollY > 50) {
      navbar && navbar.classList.add("scrolled");
    } else {
      navbar && navbar.classList.remove("scrolled");
    }
  }
  updateNav();
  window.addEventListener("scroll", updateNav, { passive: true });

  // Hamburger open/close
  if (navToggle && navMobile) {
    navToggle.addEventListener("click", () => {
      navToggle.classList.toggle("open");
      navMobile.classList.toggle("open");
      document.body.style.overflow = navMobile.classList.contains("open")
        ? "hidden"
        : "";
    });
  }
  if (navMobileClose) {
    navMobileClose.addEventListener("click", () => {
      navToggle && navToggle.classList.remove("open");
      navMobile && navMobile.classList.remove("open");
      document.body.style.overflow = "";
    });
  }
  // Mobile Events accordion toggle
  const mobileEventsToggle = document.getElementById("mobileEventsToggle");
  const mobileEventsMenu = document.getElementById("mobileEventsMenu");
  if (mobileEventsToggle && mobileEventsMenu) {
    mobileEventsToggle.addEventListener("click", () => {
      mobileEventsToggle.classList.toggle("open");
      mobileEventsMenu.classList.toggle("open");
    });
  }

  // Close mobile menu on nav link click
  document.querySelectorAll(".nav-mobile a").forEach((a) => {
    a.addEventListener("click", () => {
      navToggle && navToggle.classList.remove("open");
      navMobile && navMobile.classList.remove("open");
      document.body.style.overflow = "";
    });
  });

  // Active link highlight
  const currentPath = window.location.pathname.split("/").pop() || "index.html";
  document.querySelectorAll(".nav-menu a, .nav-mobile a").forEach((a) => {
    const href = a.getAttribute("href") || "";
    if (
      href === currentPath ||
      (currentPath === "index.html" && href === "#") ||
      href === ""
    )
      return;
    if (href && currentPath.includes(href.replace(".html", ""))) {
      a.classList.add("active");
    }
  });

  /* ----------------------------------------------------------
     2. SCROLL ANIMATION – Intersection Observer (fade-up)
  ---------------------------------------------------------- */
  const animatedEls = document.querySelectorAll(".fade-up, .fade-in, .stagger");
  if (animatedEls.length) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add("visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -40px 0px" },
    );

    animatedEls.forEach((el) => observer.observe(el));
  }

  /* ----------------------------------------------------------
     3. COUNTDOWN TIMER
  ---------------------------------------------------------- */
  const countdownEl = document.getElementById("countdown");
  if (countdownEl) {
    const target = new Date("2026-07-25T09:00:00");
    function tick() {
      const diff = target - new Date();
      if (diff <= 0) {
        countdownEl.innerHTML =
          '<span style="color:#34D399;font-weight:700">Event has started!</span>';
        return;
      }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      document.getElementById("cd-days") &&
        (document.getElementById("cd-days").textContent = String(d).padStart(
          2,
          "0",
        ));
      document.getElementById("cd-hours") &&
        (document.getElementById("cd-hours").textContent = String(h).padStart(
          2,
          "0",
        ));
      document.getElementById("cd-mins") &&
        (document.getElementById("cd-mins").textContent = String(m).padStart(
          2,
          "0",
        ));
      document.getElementById("cd-secs") &&
        (document.getElementById("cd-secs").textContent = String(s).padStart(
          2,
          "0",
        ));
    }
    tick();
    setInterval(tick, 1000);
  }

  /* ----------------------------------------------------------
     4. GALLERY LIGHTBOX
  ---------------------------------------------------------- */
  const lightbox = document.getElementById("lightbox");
  const lightboxImg = document.getElementById("lightbox-img");
  const lightboxClose = document.getElementById("lightbox-close");
  const lightboxPrev = document.getElementById("lightbox-prev");
  const lightboxNext = document.getElementById("lightbox-next");
  const galleryItems = Array.from(
    document.querySelectorAll(".gallery-item[data-src]"),
  );
  let currentIdx = 0;

  function openLightbox(idx) {
    currentIdx = idx;
    lightboxImg && (lightboxImg.src = galleryItems[idx].dataset.src);
    lightbox && lightbox.classList.add("active");
    document.body.style.overflow = "hidden";
  }
  function closeLightbox() {
    lightbox && lightbox.classList.remove("active");
    document.body.style.overflow = "";
  }
  function showPrev() {
    openLightbox((currentIdx - 1 + galleryItems.length) % galleryItems.length);
  }
  function showNext() {
    openLightbox((currentIdx + 1) % galleryItems.length);
  }

  galleryItems.forEach((item, i) => {
    item.addEventListener("click", () => openLightbox(i));
  });
  lightboxClose && lightboxClose.addEventListener("click", closeLightbox);
  lightboxPrev && lightboxPrev.addEventListener("click", showPrev);
  lightboxNext && lightboxNext.addEventListener("click", showNext);
  lightbox &&
    lightbox.addEventListener("click", (e) => {
      if (e.target === lightbox) closeLightbox();
    });
  document.addEventListener("keydown", (e) => {
    if (!lightbox || !lightbox.classList.contains("active")) return;
    if (e.key === "Escape") closeLightbox();
    if (e.key === "ArrowLeft") showPrev();
    if (e.key === "ArrowRight") showNext();
  });

  /* ----------------------------------------------------------
     RECAPTCHA v3 — get a one-time token for a named action.
     Returns '' if the site key or library is not available.
  ---------------------------------------------------------- */
  async function getRecaptchaToken(action) {
    if (!window.RECAPTCHA_SITE_KEY || typeof grecaptcha === "undefined")
      return "";
    try {
      return await new Promise((resolve, reject) =>
        grecaptcha.ready(() =>
          grecaptcha
            .execute(window.RECAPTCHA_SITE_KEY, { action })
            .then(resolve)
            .catch(reject),
        ),
      );
    } catch (_) {
      return "";
    }
  }

  /* ----------------------------------------------------------
     5. CONTACT FORM submission (send to PHP + show reason on failure)
  ---------------------------------------------------------- */
  const contactForm = document.getElementById("contactForm");
  if (contactForm) {
    const btn = contactForm.querySelector("[type=submit]");
    const successMsg = document.getElementById("successMsg");
    const errorMsg = document.getElementById("errorMsg");
    const defaultBtnText = btn ? btn.textContent.trim() : "Send Message";

    const hideFormMessages = () => {
      successMsg && successMsg.classList.remove("show");
      errorMsg && errorMsg.classList.remove("show");
    };

    contactForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!btn) return;

      hideFormMessages();
      btn.textContent = "Sending...";
      btn.disabled = true;

      try {
        if (window.location.protocol === "file:") {
          throw new Error(fileProtocolSubmitMessage);
        }

        const action =
          contactForm.getAttribute("action") || window.location.href;
        const recaptchaToken = await getRecaptchaToken("contact");
        const fd = new FormData(contactForm);
        if (recaptchaToken) fd.append("recaptcha_token", recaptchaToken);
        const response = await fetch(action, {
          method: "POST",
          headers: { Accept: "application/json" },
          body: fd,
        });

        const raw = await response.text();
        let result = null;

        try {
          result = JSON.parse(raw);
        } catch (_) {
          result = null;
        }

        const serverMessage =
          result && typeof result.message === "string"
            ? result.message.trim()
            : "";

        if (!response.ok || !result || result.status !== "success") {
          throw new Error(
            serverMessage || "Message could not be sent right now.",
          );
        }

        if (successMsg) {
          successMsg.textContent =
            serverMessage ||
            "Message sent successfully. We'll get back to you soon.";
          successMsg.classList.add("show");
        }

        contactForm.reset();
        setTimeout(
          () => successMsg && successMsg.classList.remove("show"),
          7000,
        );
      } catch (err) {
        const reason =
          err instanceof Error
            ? err.message
            : "Message could not be sent right now.";
        if (errorMsg) {
          errorMsg.textContent = reason;
          errorMsg.classList.add("show");
        }
      } finally {
        btn.textContent = defaultBtnText;
        btn.disabled = false;
      }
    });
  }

  // Registration form on event page
  const parseJsonResponse = async (response) => {
    const raw = await response.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      return null;
    }
  };

  const stepOneForm = document.getElementById("regStepOneForm");
  const paymentForm = document.getElementById("regPaymentForm");

  if (stepOneForm && paymentForm) {
    const stepPanels = Array.from(
      document.querySelectorAll("[data-step-panel]"),
    );
    const stepIndicators = Array.from(
      document.querySelectorAll("[data-step-indicator]"),
    );
    const stepOneBtn = stepOneForm.querySelector("[type=submit]");
    const paymentBtn = paymentForm.querySelector("[type=submit]");
    const stepOneErrorMsg = document.getElementById("regStepOneErrorMsg");
    const paymentErrorMsg = document.getElementById("regPaymentErrorMsg");
    const paymentSuccessMsg = document.getElementById("regPaymentSuccessMsg");
    const reviewCard = document.getElementById("registrationReview");
    const reviewGrid = document.getElementById("registrationReviewGrid");
    const backBtn = document.getElementById("regBackToStepOne");
    const registrationIdField = document.getElementById("registrationIdField");
    const paymentAmountField = document.getElementById("paymentAmountField");
    const baseAmountField = document.getElementById("baseAmountField");
    const spouseAmountField = document.getElementById("spouseAmountField");
    const totalAmountField = document.getElementById("totalAmountField");
    const selectedCategoryLabel = document.getElementById(
      "selectedCategoryLabel",
    );
    const baseAmountLabel = document.getElementById("baseAmountLabel");
    const spouseAmountLabel = document.getElementById("spouseAmountLabel");
    const totalAmountLabel = document.getElementById("totalAmountLabel");
    const spouseInput = document.getElementById("spouseOption");
    const screenshotInput = document.getElementById("paymentScreenshotInput");
    const screenshotPreview = document.getElementById(
      "paymentScreenshotPreview",
    );
    const screenshotImage = document.getElementById("paymentScreenshotImage");
    const screenshotName = document.getElementById("paymentScreenshotName");
    const screenshotRemoveBtn = document.getElementById(
      "paymentScreenshotRemove",
    );
    const feeInputs = Array.from(
      paymentForm.querySelectorAll('input[name="payment_category"]'),
    );
    const defaultStepOneText = stepOneBtn
      ? stepOneBtn.textContent.trim()
      : "Continue to Payment";
    const defaultPaymentText = paymentBtn
      ? paymentBtn.textContent.trim()
      : "Submit Payment Details";
    const hiddenFieldMap = {
      first_name: document.getElementById("paymentFirstName"),
      last_name: document.getElementById("paymentLastName"),
      email: document.getElementById("paymentEmail"),
      mobile: document.getElementById("paymentMobile"),
      qualification: document.getElementById("paymentQualification"),
      experience: document.getElementById("paymentExperience"),
      institution: document.getElementById("paymentInstitution"),
      city: document.getElementById("paymentCity"),
      area_of_interest: document.getElementById("paymentAreaOfInterest"),
      referral_source: document.getElementById("paymentReferralSource"),
      comments: document.getElementById("paymentComments"),
      terms_accepted: document.getElementById("paymentTermsAccepted"),
    };

    let savedRegistration = null;
    let paymentScreenshotObjectUrl = "";
    const DRAFT_KEY = "oe_reg_vns2026";

    // ── Resume banner ──────────────────────────────────────────
    function showResumeBanner(draft) {
      const banner = document.getElementById("regResumeBanner");
      if (!banner) return;
      const nameEl = document.getElementById("regResumeName");
      const idEl = document.getElementById("regResumeId");
      if (nameEl)
        nameEl.textContent =
          `${draft.first_name || ""} ${draft.last_name || ""}`.trim() ||
          "Delegate";
      if (idEl) idEl.textContent = draft.registration_id;
      banner.hidden = false;

      document
        .getElementById("regResumeContinue")
        ?.addEventListener("click", () => {
          savedRegistration = { ...draft };
          syncHiddenRegistrationFields();
          renderReview();
          showStep(2);
          updatePaymentSummary();
          banner.hidden = true;
          window.scrollTo({
            top: document.getElementById("registration")?.offsetTop - 80 || 0,
            behavior: "smooth",
          });
        });

      document
        .getElementById("regResumeDiscard")
        ?.addEventListener("click", () => {
          try {
            localStorage.removeItem(DRAFT_KEY);
          } catch (_) {}
          banner.hidden = true;
        });
    }

    // Check for a saved draft on page load
    try {
      const raw = localStorage.getItem(DRAFT_KEY);
      if (raw) {
        const draft = JSON.parse(raw);
        const THIRTY_DAYS = 30 * 24 * 60 * 60 * 1000;
        if (
          draft &&
          draft.registration_id &&
          Date.now() - (draft._savedAt || 0) < THIRTY_DAYS
        ) {
          showResumeBanner(draft);
        } else {
          localStorage.removeItem(DRAFT_KEY);
        }
      }
    } catch (_) {}

    const formatAmount = (amount) =>
      `Rs. ${Number(amount).toLocaleString("en-IN")}`;
    const escapeHtml = (value) =>
      String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#39;");

    const setMessage = (el, message, type = "error") => {
      if (!el) return;
      el.textContent = message;
      el.classList.toggle("show", message !== "");
      if (type === "success") {
        el.classList.add("show");
      }
    };

    const clearMessages = () => {
      [stepOneErrorMsg, paymentErrorMsg, paymentSuccessMsg].forEach((el) => {
        el && el.classList.remove("show");
      });
    };

    const clearPaymentScreenshotPreview = ({ clearInput = false } = {}) => {
      if (paymentScreenshotObjectUrl) {
        URL.revokeObjectURL(paymentScreenshotObjectUrl);
        paymentScreenshotObjectUrl = "";
      }

      if (screenshotPreview) screenshotPreview.hidden = true;
      if (screenshotImage) screenshotImage.src = "";
      if (screenshotName) screenshotName.textContent = "";
      if (clearInput && screenshotInput) screenshotInput.value = "";
    };

    const updatePaymentScreenshotPreview = () => {
      if (
        !screenshotInput ||
        !screenshotPreview ||
        !screenshotImage ||
        !screenshotName
      )
        return;

      const file = screenshotInput.files && screenshotInput.files[0];
      if (!file) {
        clearPaymentScreenshotPreview();
        return;
      }

      clearPaymentScreenshotPreview();
      paymentScreenshotObjectUrl = URL.createObjectURL(file);
      screenshotImage.src = paymentScreenshotObjectUrl;
      screenshotName.textContent = file.name;
      screenshotPreview.hidden = false;
    };

    const showStep = (stepNumber) => {
      stepPanels.forEach((panel, index) => {
        const isActive = index === stepNumber - 1;
        panel.classList.toggle("active", isActive);
      });

      stepIndicators.forEach((indicator, index) => {
        const step = index + 1;
        indicator.classList.toggle("active", step === stepNumber);
        indicator.classList.toggle("complete", step < stepNumber);
      });
    };

    const syncHiddenRegistrationFields = () => {
      if (!savedRegistration) return;

      Object.entries(hiddenFieldMap).forEach(([name, field]) => {
        if (!field) return;
        field.value =
          savedRegistration[name] || (name === "terms_accepted" ? "yes" : "");
      });

      if (registrationIdField) {
        registrationIdField.value = savedRegistration.registration_id || "";
      }
    };

    const renderReview = () => {
      if (!reviewCard || !reviewGrid || !savedRegistration) return;

      const reviewItems = [
        [
          "Delegate Name",
          `${savedRegistration.first_name || ""} ${savedRegistration.last_name || ""}`.trim(),
        ],
        ["Email", savedRegistration.email || ""],
        ["Mobile", savedRegistration.mobile || ""],
        ["Qualification", savedRegistration.qualification || ""],
        ["Institution", savedRegistration.institution || ""],
        ["City", savedRegistration.city || ""],
      ].filter(([, value]) => value);

      reviewGrid.innerHTML = reviewItems
        .map(
          ([label, value]) => `
        <div class="review-item">
          <span class="lbl">${escapeHtml(label)}</span>
          <span class="val">${escapeHtml(value)}</span>
        </div>
      `,
        )
        .join("");

      reviewCard.classList.add("show");
    };

    const UPI_VPA = "0795307A0182926.bqr@kotak";
    const UPI_PAYEE_NAME = "Ortho Edge Academic Foundation";
    const UPI_NOTE = "AGUSICON 2026 Registration";

    const updateUpiQr = (totalAmount) => {
      const img = document.getElementById("upiQrImg");
      if (!img || typeof QRCode === "undefined") return;
      const upiString =
        `upi://pay?pa=${UPI_VPA}` +
        `&pn=${encodeURIComponent(UPI_PAYEE_NAME)}` +
        `&am=${totalAmount}` +
        `&cu=INR` +
        `&tn=${encodeURIComponent(UPI_NOTE)}`;
      QRCode.toDataURL(upiString, { width: 180, margin: 2 }, (err, url) => {
        if (!err) img.src = url;
      });
    };

    const updatePaymentSummary = () => {
      const selectedFee = feeInputs.find((input) => input.checked);
      const category = selectedFee ? selectedFee.value : "other_delegate";
      // AGUSICON 2026 fee categories – read from window.AGUSICON_FEES if available
      const agusiFees = window.AGUSICON_FEES || { pg_student: 1000, other_delegate: 3000 };
      const agusiLabels = window.AGUSICON_FEE_LABELS || { pg_student: "PG Student", other_delegate: "Other Delegate" };
      const baseAmount = agusiFees[category] || 3000;
      const spouseAmount = 0;
      const totalAmount = baseAmount;

      if (selectedCategoryLabel) {
        selectedCategoryLabel.textContent = agusiLabels[category] || category;
      }
      baseAmountLabel &&
        (baseAmountLabel.textContent = formatAmount(baseAmount));
      spouseAmountLabel &&
        (spouseAmountLabel.textContent = formatAmount(spouseAmount));
      totalAmountLabel &&
        (totalAmountLabel.textContent = formatAmount(totalAmount));
      paymentAmountField &&
        (paymentAmountField.value = formatAmount(totalAmount));
      baseAmountField && (baseAmountField.value = String(baseAmount));
      spouseAmountField && (spouseAmountField.value = String(spouseAmount));
      totalAmountField && (totalAmountField.value = String(totalAmount));
      updateUpiQr(totalAmount);
    };

    const resetTwoStepRegistration = () => {
      savedRegistration = null;
      stepOneForm.reset();
      paymentForm.reset();
      clearPaymentScreenshotPreview();
      reviewCard && reviewCard.classList.remove("show");
      reviewGrid && (reviewGrid.innerHTML = "");

      Object.values(hiddenFieldMap).forEach((field) => {
        if (field)
          field.value = field.id === "paymentTermsAccepted" ? "yes" : "";
      });

      if (registrationIdField) registrationIdField.value = "";
      if (feeInputs[0]) feeInputs[0].checked = true;
      if (spouseInput) spouseInput.checked = false;
      updatePaymentSummary();
      showStep(1);
    };

    feeInputs.forEach((input) =>
      input.addEventListener("change", updatePaymentSummary),
    );
    spouseInput && spouseInput.addEventListener("change", updatePaymentSummary);
    screenshotInput &&
      screenshotInput.addEventListener(
        "change",
        updatePaymentScreenshotPreview,
      );
    screenshotRemoveBtn &&
      screenshotRemoveBtn.addEventListener("click", () => {
        clearPaymentScreenshotPreview({ clearInput: true });
        screenshotInput && screenshotInput.focus();
      });
    updatePaymentSummary();

    backBtn &&
      backBtn.addEventListener("click", () => {
        clearMessages();
        showStep(1);
      });

    stepOneForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!stepOneBtn) return;

      clearMessages();
      stepOneBtn.textContent = "Saving...";
      stepOneBtn.disabled = true;

      try {
        if (window.location.protocol === "file:") {
          throw new Error(fileProtocolSubmitMessage);
        }

        const recaptchaToken = await getRecaptchaToken("register");
        const fd = new FormData(stepOneForm);
        if (recaptchaToken) fd.append("recaptcha_token", recaptchaToken);
        const response = await fetch(
          stepOneForm.getAttribute("action") || window.location.href,
          {
            method: "POST",
            headers: { Accept: "application/json" },
            body: fd,
          },
        );

        const result = await parseJsonResponse(response);
        const serverMessage =
          result && typeof result.message === "string"
            ? result.message.trim()
            : "";

        if (!response.ok || !result || result.status !== "success") {
          throw new Error(
            serverMessage || "Registration could not be submitted right now.",
          );
        }

        savedRegistration = Object.fromEntries(
          new FormData(stepOneForm).entries(),
        );
        savedRegistration.registration_id = result.registration_id || "";

        try {
          localStorage.setItem(
            DRAFT_KEY,
            JSON.stringify({ ...savedRegistration, _savedAt: Date.now() }),
          );
        } catch (_) {}

        syncHiddenRegistrationFields();
        renderReview();
        showStep(2);
        updatePaymentSummary();
      } catch (err) {
        const reason =
          err instanceof Error
            ? err.message
            : "Registration could not be submitted right now.";
        setMessage(stepOneErrorMsg, reason);
      } finally {
        stepOneBtn.textContent = defaultStepOneText;
        stepOneBtn.disabled = false;
      }
    });

    paymentForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!paymentBtn) return;

      clearMessages();

      if (!savedRegistration) {
        setMessage(
          paymentErrorMsg,
          "Please complete step 1 before submitting payment details.",
        );
        showStep(1);
        return;
      }

      syncHiddenRegistrationFields();
      updatePaymentSummary();

      paymentBtn.textContent = "Submitting...";
      paymentBtn.disabled = true;

      try {
        if (window.location.protocol === "file:") {
          throw new Error(fileProtocolSubmitMessage);
        }

        const recaptchaTokenPayment = await getRecaptchaToken("payment");
        const fdPayment = new FormData(paymentForm);
        if (recaptchaTokenPayment)
          fdPayment.append("recaptcha_token", recaptchaTokenPayment);
        const response = await fetch(
          paymentForm.getAttribute("action") || window.location.href,
          {
            method: "POST",
            headers: { Accept: "application/json" },
            body: fdPayment,
          },
        );

        const result = await parseJsonResponse(response);
        const serverMessage =
          result && typeof result.message === "string"
            ? result.message.trim()
            : "";

        if (!response.ok || !result || result.status !== "success") {
          throw new Error(
            serverMessage ||
              "Payment details could not be submitted right now.",
          );
        }

        try {
          localStorage.removeItem(DRAFT_KEY);
        } catch (_) {}

        paymentBtn.textContent = defaultPaymentText;
        paymentBtn.disabled = false;

        if (paymentSuccessMsg) {
          paymentSuccessMsg.textContent =
            serverMessage ||
            "Payment details submitted successfully. Our team will verify your payment and contact you shortly.";
          paymentSuccessMsg.classList.add("show");
        }

        setTimeout(() => {
          paymentSuccessMsg && paymentSuccessMsg.classList.remove("show");
          resetTwoStepRegistration();
        }, 7000);
      } catch (err) {
        const reason =
          err instanceof Error
            ? err.message
            : "Payment details could not be submitted right now.";
        setMessage(paymentErrorMsg, reason);
      } finally {
        paymentBtn.textContent = defaultPaymentText;
        paymentBtn.disabled = false;
      }
    });
  } else {
    const regForm = document.getElementById("regForm");
    if (regForm) {
      const btn = regForm.querySelector("[type=submit]");
      const successMsg = document.getElementById("regSuccessMsg");
      const errorMsg = document.getElementById("regErrorMsg");
      const defaultBtnText = btn
        ? btn.textContent.trim()
        : "Submit Registration";

      const hideFormMessages = () => {
        successMsg && successMsg.classList.remove("show");
        errorMsg && errorMsg.classList.remove("show");
      };

      regForm.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (!btn) return;

        hideFormMessages();
        btn.textContent = "Submitting...";
        btn.disabled = true;

        try {
          if (window.location.protocol === "file:") {
            throw new Error(fileProtocolSubmitMessage);
          }

          const response = await fetch(
            regForm.getAttribute("action") || window.location.href,
            {
              method: "POST",
              headers: { Accept: "application/json" },
              body: new FormData(regForm),
            },
          );

          const result = await parseJsonResponse(response);
          const serverMessage =
            result && typeof result.message === "string"
              ? result.message.trim()
              : "";

          if (!response.ok || !result || result.status !== "success") {
            throw new Error(
              serverMessage || "Registration could not be submitted right now.",
            );
          }

          if (successMsg) {
            successMsg.textContent =
              serverMessage ||
              "Registration submitted successfully. We will contact you shortly.";
            successMsg.classList.add("show");
          }

          regForm.reset();
          setTimeout(
            () => successMsg && successMsg.classList.remove("show"),
            7000,
          );
        } catch (err) {
          const reason =
            err instanceof Error
              ? err.message
              : "Registration could not be submitted right now.";
          if (errorMsg) {
            errorMsg.textContent = reason;
            errorMsg.classList.add("show");
          }
        } finally {
          btn.textContent = defaultBtnText;
          btn.disabled = false;
        }
      });
    }
  }

  /* ----------------------------------------------------------
     6. Smooth scroll for anchor links
  ---------------------------------------------------------- */
  document.querySelectorAll('a[href^="#"]').forEach((a) => {
    a.addEventListener("click", (e) => {
      const href = a.getAttribute("href");
      if (href === "#") return;
      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        const offset = 80;
        const top =
          target.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top, behavior: "smooth" });
      }
    });
  });
});

/* ============================================================
   SCROLL-TO-TOP + PROGRESS RING
============================================================ */
(function () {
  const CIRCUMFERENCE = 2 * Math.PI * 20; // r=20 in SVG

  const btn = document.createElement("button");
  btn.id = "scrollTopBtn";
  btn.setAttribute("aria-label", "Scroll to top");
  btn.innerHTML = `
    <svg class="scroll-svg" viewBox="0 0 48 48" aria-hidden="true">
      <circle class="scroll-track" cx="24" cy="24" r="20"/>
      <circle class="scroll-ring" id="scrollProgressRing" cx="24" cy="24" r="20"
        stroke-dasharray="${CIRCUMFERENCE.toFixed(2)}"
        stroke-dashoffset="${CIRCUMFERENCE.toFixed(2)}"/>
    </svg>
    <svg class="scroll-chevron" width="18" height="18" viewBox="0 0 24 24"
      fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round"
      stroke-linejoin="round" aria-hidden="true">
      <polyline points="18 15 12 9 6 15"/>
    </svg>`;
  document.body.appendChild(btn);

  const ring = document.getElementById("scrollProgressRing");

  function onScroll() {
    const scrolled = window.scrollY;
    const total = document.documentElement.scrollHeight - window.innerHeight;
    const pct = total > 0 ? scrolled / total : 0;
    ring.style.strokeDashoffset = (CIRCUMFERENCE * (1 - pct)).toFixed(2);

    if (scrolled > 300) {
      btn.classList.add("visible");
    } else {
      btn.classList.remove("visible");
    }
  }

  window.addEventListener("scroll", onScroll, { passive: true });

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });
})();

/* ============================================================
   EVENT BOTTOM BAR
============================================================ */
(function () {
  const DISMISSED_KEY = "oe-bar-dismissed-vns2026";
  const EVENT_DATE = new Date("2026-07-25T09:00:00+05:30");

  if (window.location.pathname.includes("knee-masterclass-varanasi-2026"))
    return;
  if (sessionStorage.getItem(DISMISSED_KEY)) return;

  // Resolve the register link relative to current page
  const existingLink = document.querySelector(
    'a[href*="knee-masterclass-varanasi-2026"]',
  );
  const regUrl = existingLink
    ? existingLink.href.split("#")[0] + "#registration"
    : "#registration";

  const bar = document.createElement("div");
  bar.id = "eventBottomBar";
  bar.innerHTML = `
    <div class="ebar-inner">
      <div class="ebar-info">
        <span class="ebar-tag">&#128197; Upcoming Event</span>
        <span class="ebar-title">AGUSICON 2026 &mdash; National Conference of AGUSI, Bhadohi, 25&ndash;26 Jul 2026</span>
      </div>
      <div class="ebar-countdown" id="ebarCountdownWrap">
        <div class="ebc"><span id="eBarDays">--</span><em>Days</em></div>
        <div class="ebc"><span id="eBarHours">--</span><em>Hrs</em></div>
        <div class="ebc"><span id="eBarMins">--</span><em>Min</em></div>
        <div class="ebc"><span id="eBarSecs">--</span><em>Sec</em></div>
      </div>
      <a href="${regUrl}" class="ebar-cta">Register Now &#8594;</a>
      <button id="eBarClose" aria-label="Close">&#x2715;</button>
    </div>`;
  document.body.appendChild(bar);

  const scrollBtn = document.getElementById("scrollTopBtn");

  function setScrollBtnPos(barVisible) {
    if (!scrollBtn) return;
    if (barVisible) {
      scrollBtn.classList.add("above-bar");
    } else {
      scrollBtn.classList.remove("above-bar");
    }
  }

  // Show bar after short delay
  setTimeout(() => {
    bar.classList.add("ebar-visible");
    setScrollBtnPos(true);
  }, 800);

  document.getElementById("eBarClose").addEventListener("click", () => {
    bar.classList.remove("ebar-visible");
    setScrollBtnPos(false);
    sessionStorage.setItem(DISMISSED_KEY, "1");
  });

  // Countdown ticker
  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function tickCountdown() {
    const diff = EVENT_DATE - Date.now();
    if (diff <= 0) {
      document.getElementById("ebarCountdownWrap").textContent =
        "Event is live!";
      return;
    }
    const days = Math.floor(diff / 864e5);
    const hours = Math.floor((diff % 864e5) / 36e5);
    const mins = Math.floor((diff % 36e5) / 6e4);
    const secs = Math.floor((diff % 6e4) / 1e3);
    document.getElementById("eBarDays").textContent = days;
    document.getElementById("eBarHours").textContent = pad(hours);
    document.getElementById("eBarMins").textContent = pad(mins);
    document.getElementById("eBarSecs").textContent = pad(secs);
  }

  tickCountdown();
  setInterval(tickCountdown, 1000);
})();

/* ----------------------------------------------------------
   STALL ENQUIRY FORM (AGUSICON 2026)
---------------------------------------------------------- */
(function () {
  const form = document.getElementById("stallEnquiryForm");
  if (!form) return;

  const successEl = document.getElementById("stallEnquirySuccess");
  const errorEl   = document.getElementById("stallEnquiryError");
  const submitBtn = form.querySelector('button[type="submit"]');

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    if (window.location.protocol === "file:") {
      errorEl && (errorEl.style.display = "block");
      errorEl && (errorEl.textContent = fileProtocolSubmitMessage || "Open via XAMPP to submit.");
      return;
    }
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = "Submitting…"; }
    try {
      const fd = new FormData(form);
      try {
        if (window.RECAPTCHA_SITE_KEY && typeof grecaptcha !== "undefined") {
          const token = await new Promise((resolve, reject) =>
            grecaptcha.ready(() =>
              grecaptcha.execute(window.RECAPTCHA_SITE_KEY, { action: "stall_enquiry" })
                .then(resolve).catch(reject)
            )
          );
          if (token) fd.append("recaptcha_token", token);
        }
      } catch (_) {}
      const res = await fetch(form.action, { method: "POST", body: fd });
      const raw = await res.text();
      let data = null;
      try { data = JSON.parse(raw); } catch (_) {}
      if (data && data.status === "success") {
        form.reset();
        if (successEl) { successEl.style.display = "block"; }
        if (errorEl)   { errorEl.style.display   = "none"; }
      } else {
        const msg = (data && data.message) || "Could not submit enquiry. Please call +91 7838768692 directly.";
        if (errorEl) { errorEl.style.display = "block"; errorEl.textContent = msg; }
      }
    } catch (err) {
      if (errorEl) { errorEl.style.display = "block"; errorEl.textContent = "Could not submit. Please call +91 7838768692."; }
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = "Submit Enquiry"; }
    }
  });
})();
