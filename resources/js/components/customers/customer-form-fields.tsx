import { useState } from "react";
import InputError from "@/components/input-error";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { AttributesEditor } from "@/components/attributes-editor";
import type { Customer, CustomerGroup, Staff } from "@/types/customer";

type Props = {
  customer?: Partial<Customer>;
  staff: Staff[];
  customerGroups: CustomerGroup[];
  errors: Record<string, string>;
};

/**
 * Option lists mirroring the GstinType, PriceTier and ContactChannel enums.
 * Kept as data so the labels live in one place per form.
 */
const GSTIN_TYPE_OPTIONS = [
  { value: "regular", label: "Regular" },
  { value: "composition", label: "Composition" },
  { value: "unregistered", label: "Unregistered" },
  { value: "consumer", label: "Consumer (B2C)" },
] as const;

const PRICE_TIER_OPTIONS = [
  { value: "a", label: "A — list price" },
  { value: "b", label: "B — trade" },
  { value: "c", label: "C — wholesale" },
] as const;

const CONTACT_CHANNEL_OPTIONS = [
  { value: "whatsapp", label: "WhatsApp" },
  { value: "sms", label: "SMS" },
  { value: "email", label: "Email" },
  { value: "call", label: "Phone call" },
] as const;

/**
 * Renders as uncontrolled inputs (defaultValue + name) so it drops straight
 * into Inertia's <Form> component — no local state, no onChange wiring.
 *
 * Tags are the one exception: a JSON array cannot be posted from plain inputs,
 * so the comma-separated box keeps local state and emits one hidden input per
 * tag.
 */
export default function CustomerFormFields({
  customer,
  staff,
  customerGroups,
  errors,
}: Props) {
  const [tags, setTags] = useState(customer?.tags ?? []);

  return (
    <div className="space-y-6">
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="full_name">Full name</Label>
          <Input
            id="full_name"
            name="full_name"
            defaultValue={customer?.full_name}
            placeholder="e.g. Akash Badole"
            autoComplete="name"
            required
          />
          <InputError message={errors.full_name} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="mobile_number">Mobile number</Label>
          <Input
            id="mobile_number"
            name="mobile_number"
            type="tel"
            inputMode="tel"
            defaultValue={customer?.mobile_number}
            placeholder="e.g. 98765 43210"
            autoComplete="tel"
            required
          />
          <InputError message={errors.mobile_number} />
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="email">Email (optional)</Label>
          <Input
            id="email"
            name="email"
            type="email"
            defaultValue={customer?.email ?? ""}
            placeholder="name@example.com"
            autoComplete="email"
          />
          <InputError message={errors.email} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="customer_type">Customer type</Label>
          <Select
            name="customer_type"
            defaultValue={customer?.customer_type ?? "individual"}
          >
            <SelectTrigger id="customer_type" className="w-full">
              <SelectValue placeholder="Select a type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="individual">Individual</SelectItem>
              <SelectItem value="business">Business</SelectItem>
            </SelectContent>
          </Select>
          <InputError message={errors.customer_type} />
        </div>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="address">Address (optional)</Label>
        <Textarea
          id="address"
          name="address"
          defaultValue={customer?.address ?? ""}
          placeholder="Street, city, state, PIN code"
          rows={2}
          autoComplete="street-address"
        />
        <InputError message={errors.address} />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="tax_number">Tax / GST number (optional)</Label>
          <Input
            id="tax_number"
            name="tax_number"
            defaultValue={customer?.tax_number ?? ""}
            placeholder="e.g. 27AAAPZ1234C1ZV"
          />
          <InputError message={errors.tax_number} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="assigned_staff_id">Assigned staff (optional)</Label>
          <Select
            name="assigned_staff_id"
            defaultValue={
              customer?.assigned_staff_id
                ? String(customer.assigned_staff_id)
                : undefined
            }
          >
            <SelectTrigger id="assigned_staff_id" className="w-full">
              <SelectValue placeholder="Unassigned" />
            </SelectTrigger>
            <SelectContent>
              {staff.map((member) => (
                <SelectItem key={member.id} value={String(member.id)}>
                  {member.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={errors.assigned_staff_id} />
        </div>
      </div>

      {/* Commercial detail. These are real columns rather than free-form
                attributes because credit limits are enforced numerically and
                GSTIN type changes what an e-invoice may claim. */}
      <h4 className="text-sm font-medium">Commercial</h4>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="grid gap-2">
          <Label htmlFor="gstin_type">GSTIN type (optional)</Label>
          <Select
            name="gstin_type"
            defaultValue={customer?.gstin_type ?? undefined}
          >
            <SelectTrigger id="gstin_type" className="w-full">
              <SelectValue placeholder="Not set" />
            </SelectTrigger>
            <SelectContent>
              {GSTIN_TYPE_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            Unregistered and Consumer are treated as B2C on e-invoices.
          </p>
          <InputError message={errors.gstin_type} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="price_tier">Price tier (optional)</Label>
          <Select
            name="price_tier"
            defaultValue={customer?.price_tier ?? undefined}
          >
            <SelectTrigger id="price_tier" className="w-full">
              <SelectValue placeholder="Not set" />
            </SelectTrigger>
            <SelectContent>
              {PRICE_TIER_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={errors.price_tier} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="customer_group_id">Customer group (optional)</Label>
          <Select
            name="customer_group_id"
            defaultValue={
              customer?.customer_group_id
                ? String(customer.customer_group_id)
                : undefined
            }
          >
            <SelectTrigger id="customer_group_id" className="w-full">
              <SelectValue placeholder="No group" />
            </SelectTrigger>
            <SelectContent>
              {customerGroups.map((group) => (
                <SelectItem key={group.id} value={String(group.id)}>
                  {group.name}
                  {Number(group.discount_percent) > 0 &&
                    ` — ${Number(group.discount_percent)}% off`}
                  {!group.is_active && " (inactive)"}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            The group takes its percentage off every invoice line you leave
            unpriced.
          </p>
          <InputError message={errors.customer_group_id} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="preferred_contact_channel">
            Preferred contact (optional)
          </Label>
          <Select
            name="preferred_contact_channel"
            defaultValue={customer?.preferred_contact_channel ?? undefined}
          >
            <SelectTrigger id="preferred_contact_channel" className="w-full">
              <SelectValue placeholder="Any channel" />
            </SelectTrigger>
            <SelectContent>
              {CONTACT_CHANNEL_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <p className="text-xs text-muted-foreground">
            Reminders respect this choice.
          </p>
          <InputError message={errors.preferred_contact_channel} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="place_of_supply">Place of supply (optional)</Label>
          <Input
            id="place_of_supply"
            name="place_of_supply"
            maxLength={2}
            defaultValue={customer?.place_of_supply ?? ""}
            placeholder={customer?.state_code ?? "e.g. 27"}
          />
          <p className="text-xs text-muted-foreground">
            Two-digit state code used for e-invoicing. Leave blank to use the
            customer state.
          </p>
          <InputError message={errors.place_of_supply} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="credit_limit">Credit limit (optional)</Label>
          <Input
            id="credit_limit"
            name="credit_limit"
            type="number"
            min={0}
            step="0.01"
            defaultValue={customer?.credit_limit ?? ""}
            placeholder="No limit"
          />
          <p className="text-xs text-muted-foreground">
            Leave blank if this customer pays upfront.
          </p>
          <InputError message={errors.credit_limit} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="credit_days">Credit days (optional)</Label>
          <Input
            id="credit_days"
            name="credit_days"
            type="number"
            min={0}
            max={365}
            defaultValue={customer?.credit_days ?? ""}
            placeholder="e.g. 30"
          />
          <p className="text-xs text-muted-foreground">
            Fills the invoice due date automatically.
          </p>
          <InputError message={errors.credit_days} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="referral_source">Referral source (optional)</Label>
          <Input
            id="referral_source"
            name="referral_source"
            defaultValue={customer?.referral_source ?? ""}
            placeholder="Walk-in, Instagram, referral…"
          />
          <InputError message={errors.referral_source} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor="tags_input">Tags (optional)</Label>
          <Input
            id="tags_input"
            value={tags.join(", ")}
            onChange={(e) =>
              setTags(
                e.target.value
                  .split(",")
                  .map((tag) => tag.trim())
                  .filter(Boolean),
              )
            }
            placeholder="bridal, daily-wear, festival"
          />
          <p className="text-xs text-muted-foreground">Comma separated.</p>
          {tags.map((tag) => (
            <input key={tag} type="hidden" name="tags[]" value={tag} />
          ))}
          <InputError message={errors.tags} />
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <div className="grid gap-2">
          <Label htmlFor="state_code">GST state code (optional)</Label>
          <Input
            id="state_code"
            name="state_code"
            maxLength={2}
            defaultValue={customer?.state_code ?? ""}
            placeholder="e.g. 27"
          />
          <InputError message={errors.state_code} />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="birthday">Birthday (optional)</Label>
          <Input
            id="birthday"
            name="birthday"
            type="date"
            defaultValue={customer?.birthday ?? ""}
          />
          <InputError message={errors.birthday} />
        </div>
        <div className="grid gap-2">
          <Label htmlFor="anniversary">Anniversary (optional)</Label>
          <Input
            id="anniversary"
            name="anniversary"
            type="date"
            defaultValue={customer?.anniversary ?? ""}
          />
          <InputError message={errors.anniversary} />
        </div>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="notes">Notes (optional)</Label>
        <Textarea
          id="notes"
          name="notes"
          defaultValue={customer?.notes ?? ""}
          placeholder="Anything worth remembering about this customer"
          rows={3}
        />
        <InputError message={errors.notes} />
      </div>

      <AttributesEditor
        initial={customer?.attributes ?? {}}
        label="Extra attributes"
        hint="Referral source, GSTIN type, segment or anything else this business tracks."
      />

      <InputError message={errors.attributes} />
    </div>
  );
}
