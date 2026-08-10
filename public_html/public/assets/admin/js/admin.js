document.addEventListener("DOMContentLoaded", () => {
    const shell = document.querySelector("[data-admin-shell]");
    const sidebarToggle = document.querySelector("[data-admin-sidebar-toggle]");
    const sidebarClose = document.querySelector("[data-admin-sidebar-close]");
    const sidebarOverlay = document.querySelector("[data-admin-sidebar-overlay]");
    const userMenu = document.querySelector("[data-admin-user-menu]");
    const userMenuToggle = document.querySelector("[data-admin-user-menu-toggle]");

    const setSidebarOpen = (isOpen) => {
        shell?.classList.toggle("is-sidebar-open", isOpen);
        document.body.classList.toggle("admin-sidebar-open", isOpen);
        document.body.classList.toggle("admin-sidebar-locked", isOpen);
        sidebarToggle?.setAttribute("aria-expanded", isOpen ? "true" : "false");
    };

    const closeSidebar = () => setSidebarOpen(false);
    const closeUserMenu = () => {
        userMenu?.classList.remove("is-open");
        userMenuToggle?.setAttribute("aria-expanded", "false");
    };

    sidebarToggle?.addEventListener("click", () => {
        setSidebarOpen(!document.body.classList.contains("admin-sidebar-open"));
        closeUserMenu();
    });

    sidebarClose?.addEventListener("click", closeSidebar);
    sidebarOverlay?.addEventListener("click", closeSidebar);
    document.querySelectorAll(".admin-sidebar .admin-nav a").forEach((link) => {
        link.addEventListener("click", closeSidebar);
    });

    const desktopSidebarQuery = window.matchMedia("(min-width: 921px)");
    const syncSidebarForViewport = () => {
        if (desktopSidebarQuery.matches) {
            closeSidebar();
        }
    };

    if (desktopSidebarQuery.addEventListener) {
        desktopSidebarQuery.addEventListener("change", syncSidebarForViewport);
    } else if (desktopSidebarQuery.addListener) {
        desktopSidebarQuery.addListener(syncSidebarForViewport);
    }
    window.addEventListener("resize", syncSidebarForViewport, { passive: true });
    syncSidebarForViewport();

    userMenuToggle?.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = userMenu?.classList.toggle("is-open") ?? false;
        userMenuToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        closeSidebar();
    });

    document.addEventListener("click", (event) => {
        if (userMenu && !userMenu.contains(event.target)) {
            closeUserMenu();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeSidebar();
            closeUserMenu();
        }
    });

    document.querySelectorAll("[data-admin-tabs]").forEach((tabsRoot) => {
        const tabs = Array.from(tabsRoot.querySelectorAll("[data-admin-tab]"));
        const panels = Array.from(document.querySelectorAll("[data-admin-tab-panel]"));

        const activate = (target) => {
            tabs.forEach((tab) => {
                const isActive = tab.getAttribute("data-admin-tab") === target;
                tab.classList.toggle("is-active", isActive);
                tab.setAttribute("aria-selected", isActive ? "true" : "false");
            });

            panels.forEach((panel) => {
                const isActive = panel.getAttribute("data-admin-tab-panel") === target;
                panel.classList.toggle("is-active", isActive);
                panel.hidden = !isActive;
            });
        };

        tabs.forEach((tab) => {
            tab.addEventListener("click", (event) => {
                event.preventDefault();
                activate(tab.getAttribute("data-admin-tab"));
            });
        });

        if (tabs.length > 0) {
            activate(tabs.find((tab) => tab.classList.contains("is-active"))?.getAttribute("data-admin-tab") || tabs[0].getAttribute("data-admin-tab"));
        }
    });

    document.querySelectorAll(".admin-theme-presets").forEach((presetRoot) => {
        const cards = Array.from(presetRoot.querySelectorAll(".admin-preset-card"));

        const sync = () => {
            cards.forEach((card) => {
                const input = card.querySelector('input[type="radio"]');
                card.classList.toggle("is-active", input?.checked === true);
            });
        };

        cards.forEach((card) => {
            const input = card.querySelector('input[type="radio"]');

            input?.addEventListener("change", sync);
            card.addEventListener("click", () => {
                if (input && !input.disabled) {
                    input.checked = true;
                    input.dispatchEvent(new Event("change", { bubbles: true }));
                }
            });
        });

        sync();
    });

    const focusTarget = document.querySelector('[data-autofocus="true"]:not([disabled]), [autofocus]:not([disabled])');
    const canFocus = (element) => {
        if (!(element instanceof HTMLElement)) {
            return false;
        }

        const style = window.getComputedStyle(element);
        return style.display !== "none" && style.visibility !== "hidden" && element.offsetParent !== null;
    };

    const persianDateDigits = (value) => String(value ?? "")
        .replace(/[۰-۹]/g, (digit) => "۰۱۲۳۴۵۶۷۸۹".indexOf(digit))
        .replace(/[٠-٩]/g, (digit) => "٠١٢٣٤٥٦٧٨٩".indexOf(digit));

    const toPersianDigits = (value) => String(value ?? "").replace(/\d/g, (digit) => "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]);

    /*
     * Shared Persian admin validation contract.
     *
     * Browser-native validation messages depend on
     * browser / operating-system language and therefore
     * must not be exposed directly to Persian users.
     */
    const adminValidationMessage = (field) => {
        const validity = field.validity;

        if (validity.valueMissing) {
            return "تکمیل این فیلد الزامی است.";
        }

        if (validity.typeMismatch) {
            return "مقدار واردشده با نوع این فیلد سازگار نیست.";
        }

        if (validity.patternMismatch) {
            return "قالب مقدار واردشده صحیح نیست.";
        }

        if (validity.tooShort) {
            return "مقدار واردشده کوتاه‌تر از حد مجاز است.";
        }

        if (validity.tooLong) {
            return "مقدار واردشده بلندتر از حد مجاز است.";
        }

        if (validity.rangeUnderflow) {
            return "مقدار واردشده از حداقل مجاز کمتر است.";
        }

        if (validity.rangeOverflow) {
            return "مقدار واردشده از حداکثر مجاز بیشتر است.";
        }

        if (validity.stepMismatch) {
            return "مقدار واردشده با گام مجاز این فیلد سازگار نیست.";
        }

        if (validity.badInput) {
            return "مقدار واردشده معتبر نیست.";
        }

        return "مقدار واردشده معتبر نیست.";
    };

    const isAdminFormField = (field) =>
        field instanceof HTMLInputElement
        || field instanceof HTMLSelectElement
        || field instanceof HTMLTextAreaElement;

    document.addEventListener(
        "invalid",
        (event) => {
            const field = event.target;

            if (!isAdminFormField(field)) {
                return;
            }

            /*
             * Preserve application-specific custom
             * messages such as Persian date validation.
             */
            if (
                field.validity.customError
                && field.dataset.adminNativeValidation !== "1"
            ) {
                return;
            }

            field.setCustomValidity("");

            if (!field.validity.valid) {
                field.setCustomValidity(
                    adminValidationMessage(field)
                );

                field.dataset.adminNativeValidation = "1";
            }
        },
        true
    );

    const clearGeneratedValidation = (event) => {
        const field = event.target;

        if (
            !isAdminFormField(field)
            || field.dataset.adminNativeValidation !== "1"
        ) {
            return;
        }

        field.setCustomValidity("");
        delete field.dataset.adminNativeValidation;
    };

    document.addEventListener(
        "input",
        clearGeneratedValidation,
        true
    );

    document.addEventListener(
        "change",
        clearGeneratedValidation,
        true
    );

    /*
     * Visible numeric inputs explicitly participating
     * in the Persian-number contract.
     * Stored/technical normalization remains server-side.
     */
    const localizePersianNumberInput = (field) => {
        if (
            !(field instanceof HTMLInputElement)
            || !field.matches("[data-persian-number-input]")
        ) {
            return;
        }

        field.value = String(field.value ?? "")
            .replace(
                /[0-9]/g,
                (digit) =>
                    "۰۱۲۳۴۵۶۷۸۹"[Number(digit)]
            )
            .replace(
                /[٠-٩]/g,
                (digit) =>
                    "۰۱۲۳۴۵۶۷۸۹"[
                        "٠١٢٣٤٥٦٧٨٩".indexOf(digit)
                    ]
            );
    };

    document.querySelectorAll(
        "[data-persian-number-input]"
    ).forEach(localizePersianNumberInput);

    document.addEventListener(
        "input",
        (event) => {
            localizePersianNumberInput(event.target);
        },
        true
    );
    const dateDiv = (a, b) => Math.trunc(a / b);
    const dateMod = (a, b) => a - dateDiv(a, b) * b;

    const jalCal = (jy) => {
        const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
        let gy = jy + 621;
        let leapJ = -14;
        let jp = breaks[0];
        let jump = 0;
        let jm = 0;

        if (jy < jp || jy >= breaks[breaks.length - 1]) {
            throw new Error("Jalali year is out of range");
        }

        for (let i = 1; i < breaks.length; i += 1) {
            jm = breaks[i];
            jump = jm - jp;
            if (jy < jm) break;
            leapJ += dateDiv(jump, 33) * 8 + dateDiv(dateMod(jump, 33), 4);
            jp = jm;
        }

        let n = jy - jp;
        leapJ += dateDiv(n, 33) * 8 + dateDiv(dateMod(n, 33) + 3, 4);
        if (dateMod(jump, 33) === 4 && jump - n === 4) leapJ += 1;
        const leapG = dateDiv(gy, 4) - dateDiv((dateDiv(gy, 100) + 1) * 3, 4) - 150;
        const march = 20 + leapJ - leapG;
        if (jump - n < 6) n = n - jump + dateDiv(jump + 4, 33) * 33;
        let leap = dateMod(dateMod(n + 1, 33) - 1, 4);
        if (leap === -1) leap = 4;

        return { leap, gy, march };
    };

    const g2d = (gy, gm, gd) => {
        let d = dateDiv((gy + dateDiv(gm - 8, 6) + 100100) * 1461, 4)
            + dateDiv(153 * dateMod(gm + 9, 12) + 2, 5)
            + gd - 34840408;
        d = d - dateDiv(dateDiv(gy + 100100 + dateDiv(gm - 8, 6), 100) * 3, 4) + 752;
        return d;
    };

    const d2g = (jdn) => {
        let j = 4 * jdn + 139361631;
        j = j + dateDiv(dateDiv(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        const i = dateDiv(dateMod(j, 1461), 4) * 5 + 308;
        const gd = dateDiv(dateMod(i, 153), 5) + 1;
        const gm = dateMod(dateDiv(i, 153), 12) + 1;
        const gy = dateDiv(j, 1461) - 100100 + dateDiv(8 - gm, 6);
        return { gy, gm, gd };
    };

    const j2d = (jy, jm, jd) => {
        const result = jalCal(jy);
        return g2d(result.gy, 3, result.march) + (jm - 1) * 31 - dateDiv(jm, 7) * (jm - 7) + jd - 1;
    };

    const d2j = (jdn) => {
        const gregorian = d2g(jdn);
        let jy = gregorian.gy - 621;
        const result = jalCal(jy);
        const firstFarvardin = g2d(gregorian.gy, 3, result.march);
        let k = jdn - firstFarvardin;
        let jm;
        let jd;

        if (k >= 0) {
            if (k <= 185) {
                jm = 1 + dateDiv(k, 31);
                jd = dateMod(k, 31) + 1;
                return { jy, jm, jd };
            }
            k -= 186;
        } else {
            jy -= 1;
            k += 179;
            if (result.leap === 1) k += 1;
        }

        jm = 7 + dateDiv(k, 30);
        jd = dateMod(k, 30) + 1;
        return { jy, jm, jd };
    };

    const padDate = (value) => String(value).padStart(2, "0");
    const formatJalali = ({ jy, jm, jd }) => `${jy}/${padDate(jm)}/${padDate(jd)}`;
    const formatGregorian = ({ gy, gm, gd }) => `${gy}-${padDate(gm)}-${padDate(gd)}`;

    const parseJalali = (value) => {
        const normalized = persianDateDigits(value).trim().replace(/[.-]/g, "/");
        const match = normalized.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
        if (!match) return null;

        const date = { jy: Number(match[1]), jm: Number(match[2]), jd: Number(match[3]) };
        if (date.jy < 1200 || date.jy > 1700 || date.jm < 1 || date.jm > 12 || date.jd < 1) return null;

        try {
            const roundTrip = d2j(j2d(date.jy, date.jm, date.jd));
            if (roundTrip.jy !== date.jy || roundTrip.jm !== date.jm || roundTrip.jd !== date.jd) return null;
        } catch (error) {
            return null;
        }

        return date;
    };

    const parseGregorian = (value) => {
        const match = String(value ?? "").trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) return null;
        return { gy: Number(match[1]), gm: Number(match[2]), gd: Number(match[3]) };
    };

    const todayJalali = () => {
        const now = new Date();
        return d2j(g2d(now.getFullYear(), now.getMonth() + 1, now.getDate()));
    };

    const jalaliMonthLength = (jy, jm) => {
        const next = jm === 12 ? { jy: jy + 1, jm: 1 } : { jy, jm: jm + 1 };
        return j2d(next.jy, next.jm, 1) - j2d(jy, jm, 1);
    };

    const monthNames = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
    const weekdayNames = ["ش", "ی", "د", "س", "چ", "پ", "ج"];
    const datePickers = [];

    const closeDatePickers = (except = null) => {
        datePickers.forEach((picker) => {
            if (picker !== except) picker.close();
        });
    };

    document.querySelectorAll("[data-persian-datepicker]").forEach((root) => {
        const input = root.querySelector("[data-persian-date-input]");
        const output = root.querySelector("[data-persian-date-output]");
        const toggle = root.querySelector("[data-persian-date-toggle]");
        if (!(input instanceof HTMLInputElement)) return;

        const calendar = document.createElement("div");
        calendar.className = "admin-persian-calendar";
        calendar.hidden = true;
        calendar.setAttribute("role", "dialog");
        calendar.setAttribute("aria-label", "تقویم شمسی");
        root.appendChild(calendar);

        let selected = parseJalali(input.value);
        if (!selected && output instanceof HTMLInputElement) {
            const gregorian = parseGregorian(output.value);
            if (gregorian) {
                selected = d2j(g2d(gregorian.gy, gregorian.gm, gregorian.gd));
                input.value = toPersianDigits(formatJalali(selected));
            }
        }
        let view = selected || todayJalali();

        const syncValue = (date, shouldClose = false) => {
            selected = date;
            if (date) {
                input.value = toPersianDigits(formatJalali(date));
                input.setCustomValidity("");
                input.removeAttribute("aria-invalid");
                if (output instanceof HTMLInputElement) {
                    output.value = formatGregorian(d2g(j2d(date.jy, date.jm, date.jd)));
                }
            } else {
                input.value = "";
                input.setCustomValidity("");
                input.removeAttribute("aria-invalid");
                if (output instanceof HTMLInputElement) output.value = "";
            }
            input.dispatchEvent(new Event("change", { bubbles: true }));
            if (shouldClose) picker.close();
        };

        const validateInput = () => {
            if (input.value.trim() === "") {
                syncValue(null);
                return true;
            }
            const parsed = parseJalali(input.value);
            if (!parsed) {
                input.setCustomValidity("تاریخ شمسی معتبر نیست.");
                input.setAttribute("aria-invalid", "true");
                if (output instanceof HTMLInputElement) output.value = "";
                return false;
            }
            view = parsed;
            syncValue(parsed);
            return true;
        };

        const render = () => {
            const firstGregorian = d2g(j2d(view.jy, view.jm, 1));
            const firstWeekday = (new Date(firstGregorian.gy, firstGregorian.gm - 1, firstGregorian.gd).getDay() + 1) % 7;
            const days = jalaliMonthLength(view.jy, view.jm);
            const today = todayJalali();

            calendar.innerHTML = "";
            const header = document.createElement("div");
            header.className = "admin-persian-calendar__header";
            const previous = document.createElement("button");
            previous.type = "button";
            previous.className = "admin-persian-calendar__nav";
            previous.textContent = "‹";
            previous.setAttribute("aria-label", "ماه قبل");
            const title = document.createElement("strong");
            title.textContent = `${monthNames[view.jm - 1]} ${toPersianDigits(view.jy)}`;
            const next = document.createElement("button");
            next.type = "button";
            next.className = "admin-persian-calendar__nav";
            next.textContent = "›";
            next.setAttribute("aria-label", "ماه بعد");
            header.append(previous, title, next);

            const weekdays = document.createElement("div");
            weekdays.className = "admin-persian-calendar__weekdays";
            weekdayNames.forEach((name) => {
                const day = document.createElement("span");
                day.textContent = name;
                weekdays.appendChild(day);
            });

            const grid = document.createElement("div");
            grid.className = "admin-persian-calendar__grid";
            for (let empty = 0; empty < firstWeekday; empty += 1) {
                grid.appendChild(document.createElement("span"));
            }
            for (let day = 1; day <= days; day += 1) {
                const button = document.createElement("button");
                button.type = "button";
                button.textContent = toPersianDigits(day);
                button.setAttribute("aria-label", toPersianDigits(`${view.jy}/${padDate(view.jm)}/${padDate(day)}`));
                const isSelected = selected && selected.jy === view.jy && selected.jm === view.jm && selected.jd === day;
                const isToday = today.jy === view.jy && today.jm === view.jm && today.jd === day;
                button.classList.toggle("is-selected", Boolean(isSelected));
                button.classList.toggle("is-today", isToday);
                button.addEventListener("click", () => syncValue({ jy: view.jy, jm: view.jm, jd: day }, true));
                grid.appendChild(button);
            }

            const footer = document.createElement("div");
            footer.className = "admin-persian-calendar__footer";
            const todayButton = document.createElement("button");
            todayButton.type = "button";
            todayButton.textContent = "امروز";
            todayButton.addEventListener("click", () => {
                view = todayJalali();
                syncValue(view, true);
            });
            const clearButton = document.createElement("button");
            clearButton.type = "button";
            clearButton.textContent = "پاک کردن";
            clearButton.addEventListener("click", () => syncValue(null, true));
            footer.append(todayButton, clearButton);

            previous.addEventListener("click", () => {
                view = view.jm === 1 ? { ...view, jy: view.jy - 1, jm: 12 } : { ...view, jm: view.jm - 1 };
                render();
            });
            next.addEventListener("click", () => {
                view = view.jm === 12 ? { ...view, jy: view.jy + 1, jm: 1 } : { ...view, jm: view.jm + 1 };
                render();
            });

            calendar.append(header, weekdays, grid, footer);
        };

        const picker = {
            root,
            input,
            open() {
                closeDatePickers(picker);
                const parsed = parseJalali(input.value);
                view = parsed || selected || todayJalali();
                render();
                calendar.hidden = false;
                root.classList.add("is-open");
                toggle?.setAttribute("aria-expanded", "true");
            },
            close() {
                calendar.hidden = true;
                root.classList.remove("is-open");
                toggle?.setAttribute("aria-expanded", "false");
            },
            validate: validateInput,
        };
        datePickers.push(picker);

        toggle?.setAttribute("aria-expanded", "false");
        toggle?.addEventListener("click", (event) => {
            event.stopPropagation();
            calendar.hidden ? picker.open() : picker.close();
        });
        input.addEventListener("focus", () => picker.open());
        input.addEventListener("input", () => {
            input.setCustomValidity("");
            input.removeAttribute("aria-invalid");
            if (output instanceof HTMLInputElement) output.value = "";
        });
        input.addEventListener("blur", () => window.setTimeout(validateInput, 120));
        root.addEventListener("click", (event) => event.stopPropagation());
    });

    document.addEventListener("click", () => closeDatePickers());
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeDatePickers();
    });
    document.querySelectorAll("form").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const invalid = datePickers.find((picker) => form.contains(picker.root) && !picker.validate());
            if (invalid) {
                event.preventDefault();
                invalid.input.focus();
                invalid.input.reportValidity();
            }
        });
    });

    document.querySelectorAll("[data-automation-party]").forEach((party) => {
        const kind = party.querySelector('[name="party_kind[]"]');
        const role = party.querySelector('[name="party_role_code[]"]');
        const summary = party.querySelector("[data-party-summary]");
        const syncParty = () => {
            const active = Boolean(kind?.value);
            const external = kind?.value === "external";
            party.querySelectorAll("[data-party-external]").forEach((field) => { field.hidden = !active || !external; });
            party.querySelectorAll("[data-party-internal]").forEach((field) => { field.hidden = !active || external; });
            if (summary) {
                const roleLabel = role?.selectedOptions?.[0]?.textContent?.trim() || "";
                const kindLabel = kind?.selectedOptions?.[0]?.textContent?.trim() || "";
                summary.textContent = [roleLabel, kindLabel].filter(Boolean).join(" · ") || "اختیاری";
            }
        };
        kind?.addEventListener("change", syncParty);
        role?.addEventListener("change", syncParty);
        syncParty();
    });

    if (focusTarget && canFocus(focusTarget) && (!document.activeElement || document.activeElement === document.body)) {
        focusTarget.focus({ preventScroll: true });
    }
});
